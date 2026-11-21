<?php

namespace App\Actions\Payments;

use App\Models\User;
use DateTimeImmutable;
use DateTimeZone;

/** First recorded payment-touch attribution, never platform-verified identity or settlement. */
final class MerchantAnalytics
{
    public const SOURCES = ['facebook' => 'Facebook', 'tiktok' => 'TikTok', 'instagram' => 'Instagram', 'whatsapp' => 'WhatsApp', 'google' => 'Google', 'youtube' => 'YouTube', 'direct' => 'Direct', 'referral' => 'Referral / Other', 'unknown' => 'Unknown'];

    public const METHODS = ['synthetic_mpesa' => 'M-Pesa (synthetic)', 'synthetic_mixx' => 'Mixx by Yas (synthetic)', 'synthetic_airtel' => 'Airtel Money (synthetic)', 'synthetic_halo' => 'HaloPesa (synthetic)', 'synthetic_bank' => 'Bank payment (synthetic)', 'synthetic_other' => 'Other (synthetic)', 'unknown' => 'Unknown'];

    public function __construct(private readonly MockPaymentLedger $ledger, private readonly AnalyticsEvidence $evidence) {}

    public function capture(User $actor, string $merchant, string $payment, array $input, int $now): array
    {
        abort_unless(array_diff(array_keys($input), ['utm_source', 'campaign', 'referral_host', 'direct']) === [], 422);
        foreach (['utm_source', 'campaign', 'referral_host'] as $field) {
            abort_unless(! isset($input[$field]) || (is_string($input[$field]) && preg_match('/\A[a-zA-Z0-9_.-]{1,80}\z/D', $input[$field]) === 1), 422);
        }
        abort_unless(! isset($input['direct']) || is_bool($input['direct']), 422);
        $source = 'unknown';
        $provenance = 'not_recorded';
        if (isset($input['utm_source'])) {
            $value = strtolower($input['utm_source']);
            $source = array_key_exists($value, self::SOURCES) && ! in_array($value, ['direct', 'unknown'], true) ? $value : 'referral';
            $provenance = 'unverified_utm';
        } elseif (isset($input['referral_host'])) {
            $host = strtolower($input['referral_host']);
            $source = ['facebook.com' => 'facebook', 'www.facebook.com' => 'facebook', 'tiktok.com' => 'tiktok', 'instagram.com' => 'instagram', 'wa.me' => 'whatsapp', 'google.com' => 'google', 'youtube.com' => 'youtube'][$host] ?? 'referral';
            $provenance = 'unverified_first_party_referral';
        } elseif (($input['direct'] ?? false) === true) {
            $source = 'direct';
            $provenance = 'explicit_first_party_direct';
        }
        ksort($input);
        $facts = ['source' => $source, 'provenance' => $provenance, 'campaign' => $input['campaign'] ?? null, 'claims' => $input];
        $hash = hash('sha256', json_encode($facts, JSON_THROW_ON_ERROR));

        return $this->ledger->financialRead($actor, $merchant, function (PaymentStore $store) use ($merchant, $payment, $now, $facts, $hash): array {
            $row = $store->rows('SELECT p.*,a.link_id FROM payment_intents p LEFT JOIN checkout_attempts a ON a.payment_reference=p.reference WHERE p.reference=? AND p.merchant_id=?', [$payment, $merchant])[0] ?? null;
            abort_unless($row !== null, 404);
            $existing = $store->rows('SELECT source,provenance,campaign,source_hash FROM analytics_attribution WHERE payment_reference=? AND merchant_id=?', [$payment, $merchant])[0] ?? null;
            if ($existing !== null) {
                abort_unless(hash_equals($existing['source_hash'], $hash), 409);

                return $facts;
            }
            abort_unless(in_array($row['status'], ['pending', 'unknown'], true) && $now >= $row['created_at'] && $now <= $row['created_at'] + 300000, 409);
            $store->write('INSERT INTO analytics_attribution VALUES(?,?,?,?,?,?,?,?,?)', [$payment, $merchant, $facts['source'], $facts['provenance'], $facts['campaign'], $row['link_id'], $now, $hash, json_encode($facts, JSON_THROW_ON_ERROR)]);

            return $facts;
        });
    }

    public function attest(User $actor, string $merchant, string $body, string $signature, int $now): array
    {
        $facts = $this->evidence->verify($body, $signature, $now);
        abort_unless($facts['merchant_id'] === $merchant, 404);

        return $this->ledger->financialRead($actor, $merchant, function (PaymentStore $store) use ($merchant, $facts, $body, $signature, $now): array {
            $confirmation = $store->rows('SELECT c.* FROM payment_confirmations c JOIN payment_intents p ON p.reference=c.payment_reference WHERE c.id=? AND c.merchant_id=? AND c.payment_reference=? AND p.provider=? AND p.status=?', [$facts['confirmation_id'], $merchant, $facts['payment_reference'], 'fictional_mock', 'confirmed'])[0] ?? null;
            abort_unless($confirmation !== null, 404);
            abort_unless($confirmation['amount_tzs'] === $facts['amount_tzs'] && $facts['occurred_at'] >= $confirmation['confirmed_at'] && $now >= $facts['occurred_at'], 409);
            $hash = hash('sha256', $body);
            $existing = $store->rows('SELECT body_hash FROM analytics_methods WHERE payment_reference=? OR event_id=?', [$facts['payment_reference'], $facts['event_id']]);
            if ($existing !== []) {
                abort_unless(count($existing) === 1 && hash_equals($existing[0]['body_hash'], $hash), 409);

                return ['status' => 'recorded', 'method' => $facts['method']];
            }
            $store->write('INSERT INTO analytics_methods VALUES(?,?,?,?,?,?,?,?,?)', [$facts['payment_reference'], $merchant, $facts['confirmation_id'], $facts['event_id'], $facts['method'], $body, $signature, $hash, $now]);

            return ['status' => 'recorded', 'method' => $facts['method']];
        });
    }

    public function report(User $actor, string $merchant, array $input, int $now): array
    {
        [$from,$to,$range] = $this->range($input, $now);

        return $this->ledger->financialRead($actor, $merchant, function (PaymentStore $store) use ($merchant, $from, $to, $range): array {
            $sources = $this->buckets(self::SOURCES);
            $methods = $this->buckets(self::METHODS);
            $cross = $trends = [];
            $total = $count = 0;
            $after = '';
            do {
                $rows = $store->rows('SELECT c.*,a.source,a.provenance,a.campaign,a.source_hash,a.claims_body,m.method,m.body,m.signature,m.body_hash,m.received_at FROM payment_confirmations c LEFT JOIN analytics_attribution a ON a.payment_reference=c.payment_reference AND a.merchant_id=c.merchant_id LEFT JOIN analytics_methods m ON m.confirmation_id=c.id AND m.merchant_id=c.merchant_id WHERE c.merchant_id=? AND c.confirmed_at>=? AND c.confirmed_at<? AND c.id>? ORDER BY c.id LIMIT 100', [$merchant, $from, $to, $after]);
                foreach ($rows as $row) {
                    abort_unless(++$count <= 10000 && is_int($row['amount_tzs']) && $row['amount_tzs'] > 0 && $total <= intdiv(PHP_INT_MAX, 10000) - $row['amount_tzs'], 422);
                    $after = $row['id'];
                    $source = $row['source'] ?? 'unknown';
                    $method = $row['method'] ?? 'unknown';
                    abort_unless(isset($sources[$source]) && isset($methods[$method]), 409);
                    if ($row['source'] !== null) {
                        $claims = json_decode($row['claims_body'], true, 8, JSON_THROW_ON_ERROR);
                        abort_unless(hash_equals($row['source_hash'], hash('sha256', $row['claims_body'])) && $claims['source'] === $source && $claims['provenance'] === $row['provenance'] && $claims['campaign'] === $row['campaign'], 409);
                    }
                    if ($row['method'] !== null) {
                        $facts = $this->evidence->verify($row['body'], $row['signature'], $row['received_at']);
                        abort_unless(hash_equals($row['body_hash'], hash('sha256', $row['body'])) && $facts['merchant_id'] === $merchant && $facts['payment_reference'] === $row['payment_reference'] && $facts['confirmation_id'] === $row['id'] && $facts['amount_tzs'] === $row['amount_tzs'] && $facts['method'] === $method, 409);
                    }
                    $total += $row['amount_tzs'];
                    $this->increment($sources[$source], $row);
                    $this->increment($methods[$method], $row);
                    $key = $source.'|'.$method;
                    $cross[$key] ??= ['source' => $source, 'method' => $method, 'count' => 0, 'value_tzs' => 0, 'transactions' => []];
                    $this->increment($cross[$key], $row);
                    $day = (new DateTimeImmutable('@'.intdiv($row['confirmed_at'], 1000)))->setTimezone(new DateTimeZone('Africa/Nairobi'))->format('Y-m-d');
                    $trends[$day] ??= ['count' => 0, 'value_tzs' => 0, 'sources' => []];
                    $trends[$day]['count']++;
                    $trends[$day]['value_tzs'] += $row['amount_tzs'];
                    $trends[$day]['sources'][$source] = ($trends[$day]['sources'][$source] ?? 0) + $row['amount_tzs'];
                }
            } while (count($rows) === 100);
            $this->percentages($sources, $total);
            $this->percentages($methods, $total);
            ksort($trends);

            return ['range' => $range, 'from_ms' => $from, 'to_ms' => $to, 'count' => $count, 'value_tzs' => $total, 'unique_customers' => null, 'sources' => $sources, 'methods' => $methods, 'cross' => array_values($cross), 'trends' => $trends,
                'attributed_value_tzs' => $total - $sources['unknown']['value_tzs'], 'model' => 'First recorded payment touch; UTM/referral claims are unverified. Explicit Direct differs from missing evidence.', 'percentage_denominator' => 'All confirmed value, including Unknown; each dimension totals 100% when nonempty.', 'fictional' => true];
        });
    }

    private function buckets(array $labels): array
    {
        return array_map(fn (string $label): array => ['label' => $label, 'count' => 0, 'value_tzs' => 0, 'basis_points' => 0, 'unique_customers' => null, 'transactions' => []], $labels);
    }

    private function increment(array &$bucket, array $row): void
    {
        $bucket['count']++;
        $bucket['value_tzs'] += $row['amount_tzs'];
        if (count($bucket['transactions']) < 5) {
            $bucket['transactions'][] = 'payment_'.$row['payment_reference'];
        }
    }

    private function percentages(array &$buckets, int $total): void
    {
        if ($total === 0) {
            return;
        }
        $remaining = 10000;
        $remainders = [];
        foreach ($buckets as $key => &$bucket) {
            $bucket['basis_points'] = intdiv($bucket['value_tzs'] * 10000, $total);
            $remaining -= $bucket['basis_points'];
            $remainders[$key] = ($bucket['value_tzs'] * 10000) % $total;
        }
        unset($bucket);
        arsort($remainders, SORT_NUMERIC);
        foreach (array_keys($remainders) as $key) {
            if ($remaining-- <= 0) {
                break;
            }
            $buckets[$key]['basis_points']++;
        }
    }

    private function range(array $input, int $now): array
    {
        abort_unless(array_diff(array_keys($input), ['range', 'from', 'to']) === [] && $now >= 0 && $now <= 253402214399999, 422);
        $range = $input['range'] ?? 'month';
        abort_unless(is_string($range) && in_array($range, ['today', 'yesterday', 'week', 'month', 'custom'], true), 422);
        $today = (new DateTimeImmutable('@'.intdiv($now, 1000)))->setTimezone(new DateTimeZone('Africa/Nairobi'))->setTime(0, 0);
        $end = $today->modify('+1 day');
        $start = match ($range) {
            'today' => $today,'yesterday' => $today->modify('-1 day'),'week' => $today->modify('monday this week'),'month' => $today->modify('first day of this month'),default => $today
        };
        if ($range === 'yesterday') {
            $end = $today;
        }
        if ($range === 'custom') {
            $dates = [];
            foreach (['from', 'to'] as $field) {
                $value = $input[$field] ?? null;
                abort_unless(is_string($value) && preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/D', $value) === 1, 422);
                $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('Africa/Nairobi'));
                abort_unless($date !== false && $date->format('Y-m-d') === $value, 422);
                $dates[$field] = $date;
            }
            $start = $dates['from'];
            $end = $dates['to']->modify('+1 day');
        } else {
            abort_unless(! isset($input['from']) && ! isset($input['to']), 422);
        }
        abort_unless($start >= new DateTimeImmutable('1970-01-02') && $end > $start && $end->getTimestamp() - $start->getTimestamp() <= 366 * 86400, 422);

        return [$start->getTimestamp() * 1000, $end->getTimestamp() * 1000, $range];
    }
}
