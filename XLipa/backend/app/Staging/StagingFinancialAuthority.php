<?php

namespace App\Staging;

use App\Models\Merchant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/** R2.4 authority is additional to application approval, never derived from it. */
final class StagingFinancialAuthority
{
    public const VERSION = 'fictional-payments-r24-v1';

    public const ACTIONS = ['r24.products.read', 'r24.links.create', 'r24.links.disable', 'r24.widgets.create', 'r24.widgets.read', 'r24.payment.create', 'r24.payment.read', 'r24.payment.query', 'r24.mock.event', 'r24.ledger.post', 'r24.ledger.reconcile', 'r24.transactions.read', 'r24.checkout.read', 'r24.checkout.initiate'];

    public static function runtime(): array
    {
        $context = app('staging.context');
        $verified = StagingIdentity::load($context['runtime']);
        StagingIdentity::check(($verified['financial_program'] ?? null) === self::VERSION);
        foreach (['runtime', 'root', 'store', 'release', 'generation'] as $key) {
            StagingIdentity::check($context[$key] === $verified[$key]);
        }
        StagingIdentity::check(DB::connection()->getDatabaseName() === $context['root'].'/workflow.sqlite');

        return $verified;
    }

    public static function current(): array
    {
        StagingIdentity::check(PHP_SAPI === 'cli-server' && ! app()->runningInConsole() && ! app()->runningUnitTests());
        StagingIdentity::check(request()->getSchemeAndHttpHost() === StagingIdentity::ORIGIN);
        $context = self::runtime();
        $envelope = request()->attributes->get('staging.financial.command');
        StagingIdentity::check(is_array($envelope));
        $c = StagingIdentity::verified($envelope, $context['public']);
        StagingIdentity::check(in_array($c['action'], self::ACTIONS, true));
        $guest = str_starts_with($c['action'], 'r24.checkout.');
        StagingIdentity::check($guest ? auth()->guest() : auth()->id() === $c['actor']);
        $owner = User::query()->findOrFail($guest ? $c['owner'] : $c['actor']);
        $merchant = Merchant::query()->findOrFail($c['merchant']);
        Gate::forUser($owner)->authorize('manageVerification', $merchant);
        StagingIdentity::check($owner->getRawOriginal('account_status') === 'active' && $owner->email_verified_at !== null && $owner->auth_version === $c['version']);
        if (! $guest) {
            StagingIdentity::check(session('auth_version') === $owner->auth_version);
        } else {
            $binding = StagingIdentity::read($context['root'].'/customer-'.$c['customer'].'.binding.json');
            StagingIdentity::check(hash_equals($binding['session'], hash('sha256', session()->getId())));
        }
        $membership = DB::table('merchant_memberships')->where('id', $c['membership'])->where('user_id', $owner->id)->where('merchant_id', $merchant->id)->where('role', 'owner')->where('status', 'active')->whereNull('revoked_at')->sole();
        StagingIdentity::check($membership->authority_version === $c['authority_version'] && $owner->email_binding_version === $c['email_version']);
        StagingIdentity::check(DB::table('merchant_applications')->where('id', $c['application'])->where('merchant_id', $merchant->id)->where('current_slot', 1)->where('status', 'approved')->exists());
        (new StagingCapability($context))->authorize($envelope, $guest ? 0 : $owner->id, $merchant->id, $c['action'], request()->getPathInfo(), $owner->auth_version, $c['command'], false);

        return $c;
    }

    public static function allows(array $actions): bool
    {
        try {
            return in_array(self::current()['action'], $actions, true);
        } catch (\Throwable) {
            return false;
        }
    }

    public static function providerAllowed(): bool
    {
        if (PHP_SAPI === 'cli' && app()->bound('r24.custodian-event')) {
            try {
                self::runtime();

                return app('r24.custodian-event') === true;
            } catch (\Throwable) {
                return false;
            }
        }

        return self::allows(self::ACTIONS);
    }

    public static function widgetAllowed(): bool
    {
        return self::allows(['r24.widgets.create', 'r24.widgets.read']);
    }
}
