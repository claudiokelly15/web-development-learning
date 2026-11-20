<?php

namespace App\Actions\Payments;

use LogicException;
use RuntimeException;
use Illuminate\Support\Str;

/** Fictional payout commands only. No bank/mobile-money rail, network or live signing format. */
final class FictionalPayoutProvider implements PayoutProvider
{
    public int $creates = 0;

    public string $scenario = 'pending';

    private array $accepted = [];

    public function __construct(private readonly FictionalFundsEvidence $evidence)
    {
        if (PHP_SAPI !== 'cli' || getenv('XLIPA_SYNTHETIC_ONLY') !== '1' || ! app()->environment('testing')) {
            throw new LogicException('Fictional payouts require isolated testing.');
        }
    }

    public function id(): string
    {
        return 'fictional_payout';
    }

    public function create(array $withdrawal): array
    {
        $this->creates++;
        if (isset($this->accepted[$withdrawal['reference']])) {
            throw new LogicException('Ambiguous payout commands must not be retried.');
        }
        $this->accepted[$withdrawal['reference']] = $withdrawal;
        if ($this->scenario === 'unresolved') {
            throw new RuntimeException('Fictional provider accepted; acknowledgement lost.');
        }

        return ['reference' => $withdrawal['reference'], 'provider_transaction' => 'fictional_'.$withdrawal['reference'], 'status' => 'pending'];
    }

    public function eventBody(string $reference, string $status, int $time, ?string $event = null): string
    {
        $row = $this->accepted[$reference] ?? throw new LogicException('No accepted fictional payout command.');

        return $this->evidence->body(['event_id' => $event ?? (string) Str::uuid(), 'merchant_id' => $row['merchant_id'], 'withdrawal_reference' => $reference,
            'destination_reference' => $row['destination_reference'], 'amount_tzs' => $row['amount_tzs'], 'currency' => 'TZS', 'provider_transaction' => 'fictional_'.$reference, 'status' => $status, 'occurred_at' => $time]);
    }
}
