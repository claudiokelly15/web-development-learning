<?php

namespace Tests\Support;

use App\Actions\Payments\FictionalMockProvider;
use App\Actions\Payments\PaymentStore;
use App\Models\Merchant;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PDO;

trait MockPaymentJourney
{
    use MerchantDecisionJourney;

    private PDO $paymentPdo;

    private FictionalMockProvider $provider;

    private FakePaymentAdmission $paymentAdmission;

    private PaymentHttpHarness $payments;

    private function eligible(string $outcome = 'approved'): array
    {
        [$owner, $merchant, $application] = $this->submitted();
        $chief = $this->chief();
        $ref = $this->decisions->prepare($chief, $application, session()->getId(), $outcome, $outcome === 'rejected' ? 'requirements_not_met' : null);
        $this->requestDecision($merchant, $application->id, $ref)->assertOk()->assertExactJson(['status' => $outcome]);
        $this->signIn($owner->fresh());
        $this->paymentPdo = new PDO('sqlite::memory:');
        PaymentStoreFixture::install($this->paymentPdo);
        $this->provider = new FictionalMockProvider(str_repeat('F', 32));
        $this->paymentAdmission = new FakePaymentAdmission;
        $this->paymentAdmission->actor = $owner->id;
        $this->paymentAdmission->merchant = $merchant->id;
        $this->payments = new PaymentHttpHarness(new PaymentStore($this->paymentPdo), $this->provider, $this->paymentAdmission);
        $this->payments->register();

        return [$owner, $merchant, $application];
    }

    private function createPayment(Merchant $merchant, array $changes = []): TestResponse
    {
        session()->save();
        $this->withCookie(config('session.cookie'), session()->getId())->withCredentials();

        return $this->postJson('/merchants/'.$merchant->id.'/__isolated-payments', ['_token' => session()->token(), 'amount_tzs' => 12500, 'currency' => 'TZS', 'idempotency_key' => 'fictional-order-0001', 'order_reference' => 'ORDER_001', ...$changes]);
    }

    private function receiveEvent(string $body, ?string $signature = null): TestResponse
    {
        return $this->call('POST', '/__isolated-provider-events', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_X_FICTIONAL_SIGNATURE' => $signature ?? $this->provider->signature($body)], $body);
    }

    private function event(string $reference, string $result = 'ok', int $sequence = 1, ?string $id = null): string
    {
        return $this->provider->eventBody($reference, $result, $sequence, $this->paymentAdmission->time, $id ?? (string) Str::uuid());
    }
}
