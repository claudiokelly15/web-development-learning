<?php

namespace App\Staging;

use App\Actions\Payments\FictionalMockProvider;
use App\Actions\Payments\MerchantDeveloperTools;
use App\Actions\Payments\MerchantWallet;
use App\Actions\Payments\MockPaymentLedger;
use App\Actions\Payments\PaymentEngine;
use App\Actions\Payments\PaymentLinkService;
use App\Actions\Payments\PaymentStore;
use Closure;
use PDO;

/** Request-local factory. PaymentStore/provider are never bound in operational or global containers. */
final class StagingFinancialServices
{
    public readonly PaymentStore $store;
    public readonly PaymentEngine $payments;
    public readonly MockPaymentLedger $ledger;
    public readonly PaymentLinkService $links;
    public readonly MerchantDeveloperTools $widgets;
    public readonly MerchantWallet $wallet;
    public readonly FictionalMockProvider $provider;

    public function __construct()
    {
        StagingFinancialAuthority::current();
        $context = StagingFinancialAuthority::runtime();
        $pdo = new PDO('sqlite:'.StagingIdentity::path($context['root'].'/financial.sqlite'));
        $pdo->exec('PRAGMA foreign_keys=ON; PRAGMA recursive_triggers=ON; PRAGMA busy_timeout=2000');
        FinancialSchema::assertReady($pdo, $context);
        $this->store = new PaymentStore($pdo);
        $this->provider = self::provider($context);
        $this->payments = new PaymentEngine($this->store, $this->provider, new StagingPaymentAdmission);
        $this->ledger = new MockPaymentLedger($this->payments, new StagingLedgerAdmission);
        $this->links = new PaymentLinkService($this->payments, $this->store, $this->ledger, new StagingLinkAdmission);
        $this->widgets = new MerchantDeveloperTools($this->payments, $this->store, $this->links, fn (): int => (int) floor(microtime(true) * 1000));
        $this->wallet = new MerchantWallet($this->ledger);
    }

    public function run(Closure $work): mixed
    {
        return $this->payments->withCapabilityGuard(fn (): array => StagingFinancialAuthority::current(), fn (): mixed => $work($this));
    }

    public static function provider(array $context): FictionalMockProvider
    {
        StagingIdentity::check(StagingFinancialAuthority::providerAllowed());
        $keys = StagingIdentity::read($context['root'].'/r24-keys.json');

        return new FictionalMockProvider(base64_decode($keys['mock'], true), function (string $operation, string $reference, ?array $value) use ($context): array {
            StagingIdentity::check((bool) preg_match('/^[a-f0-9-]{36}$/D', $reference));
            $path = StagingIdentity::path($context['root']).'/mock-accepted-'.$reference.'.json';
            if ($operation === 'write') {
                $handle = fopen($path, 'x');
                StagingIdentity::check($handle !== false);
                try {
                    $body = StagingIdentity::encode(['runtime' => $context['runtime'], 'store' => $context['store'], 'payment' => $value]);
                    StagingIdentity::check(fwrite($handle, $body) === strlen($body) && fflush($handle) && fsync($handle));
                } finally {
                    fclose($handle);
                }
            }
            $accepted = StagingIdentity::read($path);
            StagingIdentity::check($accepted['runtime'] === $context['runtime'] && $accepted['store'] === $context['store'] && $accepted['payment']['reference'] === $reference);

            return $accepted['payment'];
        });
    }
}
