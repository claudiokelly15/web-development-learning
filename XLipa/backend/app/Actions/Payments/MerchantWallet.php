<?php

namespace App\Actions\Payments;

use App\Models\User;
use DateTimeImmutable;
use DateTimeZone;

/** Read-only projection of the existing P4/P5/P6 store. No operational binding. */
final class MerchantWallet
{
    private const RECORDS = "SELECT 'payment_'||p.reference AS record_id,p.reference AS payment_reference,p.created_at,p.updated_at,p.amount_tzs,p.currency,p.status AS payment_status,p.dispatch_state,p.provider,
        a.id AS checkout_attempt_id,l.id AS payment_link_id,l.description,c.confirmed_at,j.id AS journal_id,j.posted_at
        FROM payment_intents p LEFT JOIN checkout_attempts a ON a.payment_reference=p.reference
        LEFT JOIN payment_links l ON l.id=a.link_id AND l.merchant_id=p.merchant_id
        LEFT JOIN payment_confirmations c ON c.payment_reference=p.reference AND c.merchant_id=p.merchant_id
        LEFT JOIN ledger_journals j ON j.payment_reference=p.reference AND j.merchant_id=p.merchant_id
        WHERE p.merchant_id=?
        UNION ALL
        SELECT 'checkout_'||a.id,NULL,a.created_at,a.created_at,l.amount_tzs,l.currency,'unresolved',NULL,NULL,a.id,l.id,l.description,NULL,NULL,NULL
        FROM checkout_attempts a JOIN payment_links l ON l.id=a.link_id WHERE l.merchant_id=? AND a.payment_reference IS NULL";

    public function __construct(private readonly MockPaymentLedger $ledger) {}

    public function overview(User $actor, string $merchant): array
    {
        return $this->ledger->financialRead($actor, $merchant, function (PaymentStore $store, array $position) use ($merchant): array {
            $states = [];
            foreach ($store->rows('SELECT status,count(*) AS count,sum(amount_tzs) AS requested_tzs FROM payment_intents WHERE merchant_id=? GROUP BY status', [$merchant]) as $row) {
                abort_unless(is_int($row['requested_tzs']) && $row['requested_tzs'] >= 0, 409);
                $states[$row['status']] = ['count' => $row['count'], 'requested_tzs' => $row['requested_tzs']];
            }
            $reservations = $store->rows('SELECT count(*) AS count FROM checkout_attempts a JOIN payment_links l ON l.id=a.link_id WHERE l.merchant_id=? AND a.payment_reference IS NULL', [$merchant])[0]['count'];

            return ['position' => $position, 'payment_states' => $states, 'unresolved_checkout_reservations' => $reservations];
        });
    }

    public function history(User $actor, string $merchant, array $input): array
    {
        [$filters, $page, $perPage, $where, $bindings] = $this->filters($input);

        return $this->ledger->financialRead($actor, $merchant, function (PaymentStore $store) use ($merchant, $filters, $page, $perPage, $where, $bindings): array {
            $sql = 'WITH records AS ('.self::RECORDS.') SELECT * FROM records WHERE '.$where;
            $parameters = [$merchant, $merchant, ...$bindings];
            $total = $store->rows('SELECT count(*) AS count FROM ('.$sql.')', $parameters)[0]['count'];
            $rows = $store->rows($sql.' ORDER BY created_at DESC,record_id DESC LIMIT ? OFFSET ?', [...$parameters, $perPage, ($page - 1) * $perPage]);

            return ['transactions' => array_map($this->present(...), $rows), 'filters' => $filters,
                'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'last_page' => $total === 0 ? 1 : intdiv($total - 1, $perPage) + 1]];
        });
    }

    public function detail(User $actor, string $merchant, string $record): array
    {
        abort_unless(preg_match('/\A(?:payment|checkout)_[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}\z/D', $record) === 1, 404);

        return $this->ledger->financialRead($actor, $merchant, function (PaymentStore $store) use ($merchant, $record): array {
            $rows = $store->rows('WITH records AS ('.self::RECORDS.') SELECT * FROM records WHERE record_id=? LIMIT 2', [$merchant, $merchant, $record]);
            abort_unless(count($rows) === 1, 404);

            return ['transaction' => $this->present($rows[0])];
        });
    }

    private function present(array $row): array
    {
        return [...$row, 'status' => $row['payment_status'] === 'unknown' ? 'unresolved' : $row['payment_status'],
            'accounting_status' => $row['journal_id'] !== null ? 'posted' : ($row['confirmed_at'] !== null ? 'not_posted' : 'not_eligible'),
            'settlement_status' => 'unproven', 'withdrawable_tzs' => 0];
    }

    private function filters(array $input): array
    {
        abort_unless(array_diff(array_keys($input), ['from', 'to', 'status', 'reference', 'page', 'per_page']) === [], 422);
        foreach ($input as $value) {
            abort_unless(is_string($value), 422);
        }
        $filters = ['from' => $input['from'] ?? '', 'to' => $input['to'] ?? '', 'status' => $input['status'] ?? '', 'reference' => $input['reference'] ?? ''];
        abort_unless(in_array($filters['status'], ['', 'pending', 'confirmed', 'failed', 'unresolved'], true)
            && preg_match('/\A[a-f0-9-]{0,36}\z/D', $filters['reference']) === 1, 422);
        $page = $input['page'] ?? '1';
        $perPage = $input['per_page'] ?? '20';
        abort_unless(preg_match('/\A[1-9][0-9]{0,4}\z/D', $page) === 1 && (int) $page <= 10000 && in_array($perPage, ['10', '20', '50'], true), 422);
        $conditions = ['1=1'];
        $bindings = [];
        $from = $this->day($filters['from']);
        $to = $this->day($filters['to']);
        abort_unless($from === null || $to === null || $from <= $to, 422);
        if ($from !== null) {
            $conditions[] = 'created_at>=?';
            $bindings[] = $from;
        }
        if ($to !== null) {
            $conditions[] = 'created_at<?';
            $bindings[] = $to + 86400000;
        }
        if ($filters['status'] !== '') {
            if ($filters['status'] === 'unresolved') {
                $conditions[] = "payment_status IN ('unknown','unresolved')";
            } else {
                $conditions[] = 'payment_status=?';
                $bindings[] = $filters['status'];
            }
        }
        if ($filters['reference'] !== '') {
            $conditions[] = 'payment_reference LIKE ?';
            $bindings[] = $filters['reference'].'%';
        }

        return [$filters, (int) $page, (int) $perPage, implode(' AND ', $conditions), $bindings];
    }

    private function day(string $date): ?int
    {
        if ($date === '') {
            return null;
        }
        abort_unless(preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/D', $date) === 1, 422);
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone('Africa/Nairobi'));
        abort_unless($parsed !== false && $parsed->format('Y-m-d') === $date && $parsed->getTimestamp() >= 0, 422);

        return $parsed->getTimestamp() * 1000;
    }
}
