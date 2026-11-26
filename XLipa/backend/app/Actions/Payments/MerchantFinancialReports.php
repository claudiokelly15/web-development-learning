<?php

namespace App\Actions\Payments;

use App\Models\User;
use Illuminate\Support\Str;

/** Read-only, bounded exports over the same authenticated P5/P8 evidence, not demo HTML. */
final class MerchantFinancialReports
{
    public function __construct(private readonly MockPaymentLedger $ledger, private readonly LedgerLifecycle $funds, private readonly MerchantAnalytics $analytics) {}

    public function report(User $actor, string $merchant, array $input, int $now): array
    {
        abort_unless(array_diff(array_keys($input), ['range', 'from', 'to', 'status', 'method', 'source']) === [], 422);
        foreach ($input as $value) {
            abort_unless(is_string($value), 422);
        }
        foreach (['from', 'to'] as $field) {
            if (($input[$field] ?? null) === '') {
                unset($input[$field]);
            }
        }
        $status = $input['status'] ?? '';
        $method = $input['method'] ?? '';
        $source = $input['source'] ?? '';
        abort_unless(in_array($status, ['', 'pending', 'confirmed', 'failed', 'unknown'], true)
            && ($method === '' || isset(MerchantAnalytics::METHODS[$method])) && ($source === '' || isset(MerchantAnalytics::SOURCES[$source])), 422);
        $analytics = $this->analytics->report($actor, $merchant, array_intersect_key($input, array_flip(['range', 'from', 'to'])), $now);
        $result = $this->ledger->financialRead($actor, $merchant, function (PaymentStore $store, array $position) use ($merchant, $analytics, $status, $method, $source): array {
            $rows = $store->rows("SELECT p.reference,p.amount_tzs,p.status,p.currency,c.id AS confirmation_id,coalesce(c.confirmed_at,p.created_at) AS occurred_at,coalesce(m.method,'unknown') AS method,coalesce(a.source,'unknown') AS source,coalesce(a.provenance,'not_recorded') AS provenance,l.id AS link_id,l.description FROM payment_intents p LEFT JOIN payment_confirmations c ON c.payment_reference=p.reference AND c.merchant_id=p.merchant_id LEFT JOIN analytics_methods m ON m.payment_reference=p.reference AND m.merchant_id=p.merchant_id LEFT JOIN analytics_attribution a ON a.payment_reference=p.reference AND a.merchant_id=p.merchant_id LEFT JOIN checkout_attempts x ON x.payment_reference=p.reference LEFT JOIN payment_links l ON l.id=x.link_id AND l.merchant_id=p.merchant_id WHERE p.merchant_id=? AND coalesce(c.confirmed_at,p.created_at)>=CAST(? AS INTEGER) AND coalesce(c.confirmed_at,p.created_at)<CAST(? AS INTEGER) ORDER BY occurred_at,p.reference LIMIT 10001", [$merchant, $analytics['from_ms'], $analytics['to_ms']]);
            abort_unless(count($rows) <= 10000, 422, 'Narrow the reporting period.');
            $rows = array_values(array_filter($rows, fn (array $row): bool => ($status === '' || $row['status'] === $status) && ($method === '' || $row['method'] === $method) && ($source === '' || $row['source'] === $source)));
            $confirmed = 0;
            foreach ($rows as $row) {
                abort_unless(is_int($row['amount_tzs']) && $row['amount_tzs'] > 0 && isset(MerchantAnalytics::METHODS[$row['method']],MerchantAnalytics::SOURCES[$row['source']]), 409);
                if ($row['status'] === 'confirmed') {
                    abort_unless($confirmed <= PHP_INT_MAX - $row['amount_tzs'], 422);
                    $confirmed += $row['amount_tzs'];
                }
            }

            return ['transactions' => $rows, 'confirmed_tzs' => $confirmed, 'position' => $position];
        });
        $funds = $this->funds->run($actor, $merchant, 'read', function (PaymentStore $store, array $position) use ($merchant, $analytics): array {
            $settlements = $store->rows("SELECT id,amount_tzs,posted_at,source_journal FROM ledger_lifecycle_journals WHERE merchant_id=? AND kind='settlement' AND posted_at>=? AND posted_at<? ORDER BY posted_at,id LIMIT 10001", [$merchant, $analytics['from_ms'], $analytics['to_ms']]);
            $withdrawals = $store->rows("SELECT w.reference,w.amount_tzs,w.destination_mask,w.created_at,CASE WHEN j.kind='payout' THEN 'confirmed_fictional_payout' WHEN j.kind='release' THEN 'failed_reconciled' ELSE 'pending_or_unresolved' END AS status,j.id AS evidence FROM funds_withdrawals w LEFT JOIN ledger_lifecycle_journals j ON j.related_withdrawal=w.reference WHERE w.merchant_id=? AND w.created_at>=? AND w.created_at<? ORDER BY w.created_at,w.reference LIMIT 10001", [$merchant, $analytics['from_ms'], $analytics['to_ms']]);
            abort_unless(count($settlements) <= 10000 && count($withdrawals) <= 10000, 422);

            return ['settlements' => $settlements, 'withdrawals' => $withdrawals, 'position' => $position];
        });

        return [...$result, 'merchant_id' => $merchant, 'filters' => $input, 'from_ms' => $analytics['from_ms'], 'to_ms' => $analytics['to_ms'], 'analytics' => $analytics, 'funds' => $funds, 'generated_at' => $now,
            'limitations' => 'Synthetic evidence only. Confirmed payments are not settled funds. Payment filters apply to transactions; settlements and withdrawal requests use their own EAT dates. Balance and analytics panels are unfiltered service snapshots. No unique-customer, fee, revenue or health inference.'];
    }

    public function export(User $actor, string $merchant, array $input, int $now, string $format): array
    {
        abort_unless(in_array($format, ['csv', 'pdf', 'xlsx'], true), 404);
        $report = $this->report($actor, $merchant, $input, $now);
        $reference = (string) Str::uuid();
        $rows = [['XLipa merchant financial report', $reference], ['Merchant', $merchant], ['Period start EAT', MerchantNotificationService::eat($report['from_ms'])], ['Period end EAT (exclusive)', MerchantNotificationService::eat($report['to_ms'])], ['Generated EAT', MerchantNotificationService::eat($now)], ['Filters', json_encode($input, JSON_THROW_ON_ERROR)], ['Limitations', $report['limitations']], [], ['Payment reference', 'Amount TZS', 'Status', 'EAT', 'Method', 'Source', 'Attribution evidence', 'Link', 'Description']];
        foreach ($report['transactions'] as $row) {
            $rows[] = [$row['reference'], $row['amount_tzs'], $row['status'], MerchantNotificationService::eat($row['occurred_at']), $row['method'], $row['source'], $row['provenance'], $row['link_id'], $row['description']];
        }
        $rows[] = ['Filtered confirmed payment total TZS', $report['confirmed_tzs']];
        $rows[] = ['Financial categories', 'Unfiltered lifetime snapshots; fictional evidence only'];
        foreach ($report['funds']['position'] as $key => $value) {
            $rows[] = [$key, $value];
        }
        $rows[] = ['Fees / refunds / reversals / merchant net', 'Unavailable: no approved fee or adjustment evidence; not assumed zero.'];
        $rows[] = ['Settlement evidence', 'Amount TZS', 'Posted EAT', 'Source journal'];
        foreach ($report['funds']['settlements'] as $row) {
            $rows[] = [$row['id'], $row['amount_tzs'], MerchantNotificationService::eat($row['posted_at']), $row['source_journal']];
        }
        $rows[] = ['Withdrawal reference', 'Requested TZS', 'Masked destination', 'Requested EAT', 'Outcome', 'Evidence'];
        foreach ($report['funds']['withdrawals'] as $row) {
            $rows[] = [$row['reference'], $row['amount_tzs'], $row['destination_mask'], MerchantNotificationService::eat($row['created_at']), $row['status'], $row['evidence']];
        }
        $content = match ($format) {
            'csv' => FinancialReportExport::csv($rows),
            'xlsx' => FinancialReportExport::xlsx($rows),
            'pdf' => FinancialReportExport::pdf('Merchant financial report', array_map(fn (array $row): string => implode(' | ', array_map(fn (mixed $value): string => (string) ($value ?? ''), $row)), $rows)),
        };
        $this->ledger->financialRead($actor, $merchant, function (PaymentStore $store) use ($merchant, $actor, $reference, $content, $now): array {
            $store->write('INSERT INTO p10_downloads VALUES(?,?,?,?,?,?)', [(string) Str::uuid(), $merchant, $actor->id, $reference, hash('sha256', $content), $now]);

            return [];
        });

        return ['reference' => $reference, 'content' => $content];
    }

    public function document(User $actor, string $merchant, string $kind, string $source, int $now): array
    {
        abort_unless(in_array($kind, ['payment', 'settlement'], true), 404);
        $work = function (PaymentStore $store) use ($actor, $merchant, $kind, $source, $now): array {
            $row = $kind === 'payment'
                ? ($store->rows("SELECT c.payment_reference AS reference,c.amount_tzs,c.confirmed_at AS occurred_at FROM payment_confirmations c JOIN payment_intents p ON p.reference=c.payment_reference AND p.merchant_id=c.merchant_id WHERE c.id=? AND c.merchant_id=? AND p.status='confirmed'", [$source, $merchant])[0] ?? null)
                : ($store->rows("SELECT j.id AS reference,j.amount_tzs,j.posted_at AS occurred_at FROM ledger_lifecycle_journals j JOIN ledger_lifecycle_seals s ON s.journal_id=j.id WHERE j.id=? AND j.merchant_id=? AND j.kind='settlement'", [$source, $merchant])[0] ?? null);
            abort_unless($row !== null, 404);
            $old = $store->rows('SELECT id,content,content_hash FROM p10_documents WHERE merchant_id=? AND kind=? AND source_id=?', [$merchant, $kind, $source])[0] ?? null;
            if ($old === null) {
                $id = (string) Str::uuid();
                $pdf = FinancialReportExport::pdf($kind === 'payment' ? 'Confirmed payment receipt' : 'Settlement evidence', ['Document: '.$id, 'Merchant: '.$merchant, 'Reference: '.$row['reference'], 'TZS: '.$row['amount_tzs'], 'Recorded: '.MerchantNotificationService::eat($row['occurred_at']), 'Fictional evidence only. Payment confirmation is not settlement or payout. No real money movement.']);
                $store->write('INSERT INTO p10_documents VALUES(?,?,?,?,?,?,?)', [$id, $merchant, $kind, $source, $pdf, hash('sha256', $pdf), $now]);
                $old = ['id' => $id, 'content' => $pdf, 'content_hash' => hash('sha256', $pdf)];
            }
            abort_unless(hash_equals($old['content_hash'], hash('sha256', $old['content'])), 409);
            $store->write('INSERT INTO p10_downloads VALUES(?,?,?,?,?,?)', [(string) Str::uuid(), $merchant, $actor->id, $old['id'], $old['content_hash'], $now]);

            return ['reference' => $old['id'], 'content' => $old['content']];
        };

        return $kind === 'payment' ? $this->ledger->financialRead($actor, $merchant, $work) : $this->funds->run($actor, $merchant, 'read', $work);
    }
}
