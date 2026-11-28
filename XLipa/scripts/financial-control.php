<?php

// Local fictional custodian only. No web route loads this file.
require __DIR__.'/../backend/vendor/autoload.php';

use App\Actions\Payments\PaymentStore;
use App\Models\User;
use App\Staging\StagingCommandState;
use App\Staging\StagingFinancialAuthority as Authority;
use App\Staging\StagingFinancialServices;
use App\Staging\StagingIdentity as Identity;
use App\Staging\StagingRuntime;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function saveFinancialJson(string $path, array $data): void
{
    Identity::check(! file_exists($path.'.new'));
    if (file_exists($path)) {
        Identity::path($path);
    }
    $handle = fopen($path.'.new', 'x');
    Identity::check($handle !== false);
    try {
        $body = Identity::encode($data);
        Identity::check(fwrite($handle, $body) === strlen($body) && fflush($handle) && fsync($handle));
    } finally {
        fclose($handle);
    }
    Identity::check(rename($path.'.new', $path));
}

try {
    Identity::check(PHP_SAPI === 'cli');
    $context = Identity::load($argv[1] ?? '');
    Identity::check(($context['financial_program'] ?? null) === Authority::VERSION);
    StagingRuntime::application($context);
    Authority::runtime();
    $root = $context['root'];
    $pdo = new PDO('sqlite:'.Identity::path($root.'/financial.sqlite'));
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $operation = $argv[2] ?? '';
    if ($operation === 'status') {
        Identity::check(! app()->bound(PaymentStore::class));
        $counts = [];
        foreach (['payment_links', 'checkout_attempts', 'payment_intents', 'payment_events', 'payment_confirmations', 'ledger_journals', 'ledger_postings', 'ledger_seals', 'api_credentials', 'merchant_webhook_deliveries', 'funds_withdrawals'] as $table) {
            $counts[$table] = (int) $pdo->query('SELECT count(*) FROM '.$table)->fetchColumn();
        }
        $balance = $pdo->query('SELECT direction,COALESCE(sum(amount_tzs),0) AS amount FROM ledger_postings GROUP BY direction ORDER BY direction')->fetchAll();
        echo Identity::encode(['program' => Authority::VERSION, 'counts' => $counts, 'balanced_postings' => $balance, 'operational_store_bound' => false, 'withdrawable_tzs' => 0]).PHP_EOL;
        exit;
    }
    $user = User::where('email', $argv[3] ?? '')->firstOrFail();
    Identity::check(str_ends_with($user->email, '@example.test') && $user->getRawOriginal('account_status') === 'active' && $user->email_verified_at !== null);
    $member = DB::table('merchant_memberships')->where('user_id', $user->id)->where('role', 'owner')->where('status', 'active')->whereNull('revoked_at')->sole();
    $merchant = $member->merchant_id;
    $application = DB::table('merchant_applications')->where('merchant_id', $merchant)->where('current_slot', 1)->where('status', 'approved')->sole();
    $base = '/merchants/'.$merchant.'/payment-products';
    $secret = base64_decode(Identity::read(Identity::anchor().'/issuer-secret.json')['secret'], true);
    $make = function (string $action, string $resource, ?string $command, array $extra = [], int $ttl = 600) use ($context, $user, $member, $application, $merchant, $secret): array {
        $c = array_intersect_key($context, array_flip(['namespace', 'issuer', 'runtime', 'release', 'generation', 'store']));
        $c = [...$c, 'id' => bin2hex(random_bytes(16)), 'actor' => $user->id, 'merchant' => $merchant, 'action' => $action, 'resource' => $resource,
            'version' => $user->auth_version, 'email_version' => $user->email_binding_version, 'membership' => $member->id, 'authority_version' => $member->authority_version,
            'application' => $application->id, 'command' => $command, 'issued' => time(), 'expires' => min(time() + $ttl, $context['expires']), ...$extra];

        return Identity::signed($c, $secret);
    };
    $grants = Identity::read($root.'/capabilities.json');
    if ($operation === 'checkout') {
        $token = $argv[4] ?? '';
        Identity::check((bool) preg_match('/^[a-f0-9]{64}$/D', $token));
        $statement = $pdo->prepare('SELECT * FROM payment_links WHERE public_token=? AND merchant_id=?');
        $statement->execute([$token, $merchant]);
        $link = $statement->fetch();
        Identity::check($link !== false && $link['state'] === 'active' && ($link['expires_at'] === null || $link['expires_at'] > (int) floor(microtime(true) * 1000)));
        $customer = bin2hex(random_bytes(16));
        $bundle = ['customer' => $customer];
        foreach (['r24.checkout.read', 'r24.checkout.initiate'] as $action) {
            $bundle[$action] = $make($action, '/fictional-checkout/'.$token, null, ['actor' => 0, 'owner' => $user->id, 'customer' => $customer, 'replay_policy' => 'session-nonce-v1']);
        }
        saveFinancialJson($root.'/checkout-'.$token.'.json', $bundle);
        echo Identity::encode(['customer' => $customer, 'url' => Identity::ORIGIN.'/fictional-checkout/'.$token]).PHP_EOL;
        exit;
    }
    $extra = [];
    $command = null;
    if ($operation === 'event' || $operation === 'event-repeat') {
        $reference = $argv[4] ?? '';
        Identity::check((bool) preg_match('/^[a-f0-9-]{36}$/D', $reference));
        $statement = $pdo->prepare('SELECT merchant_id FROM payment_intents WHERE reference=?');
        $statement->execute([$reference]);
        Identity::check($statement->fetchColumn() === $merchant);
        $action = 'r24.mock.event';
        $resource = $base.'/payments/'.$reference.'/mock-confirmation';
        $command = bin2hex(random_bytes(16));
        if ($operation === 'event-repeat') {
            $previous = Identity::verified($grants[$user->id.'|'.$action.'|'.$merchant], $context['public']);
            Identity::check($previous['resource'] === $resource);
            $event = Identity::read($root.'/mock-event-'.$previous['command'].'.json');
        } else {
            $result = $argv[5] ?? '';
            Identity::check(in_array($result, ['waiting', 'ok', 'declined', 'uncertain'], true));
            $sequence = filter_var($argv[6] ?? '1', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            Identity::check($sequence !== false);
            app()->instance('r24.custodian-event', true);
            $provider = StagingFinancialServices::provider($context);
            $body = $provider->eventBody($reference, $result, $sequence, (int) floor(microtime(true) * 1000), (string) Str::uuid());
            $event = ['body' => $body, 'signature' => $provider->signature($body)];
        }
        $extra['event_hash'] = hash('sha256', $event['body']);
    } else {
        Identity::check($operation === 'grant');
        $action = $argv[4] ?? '';
        $id = $argv[5] ?? '';
        $suffix = match ($action) {
            'r24.products.read' => '', 'r24.links.create' => '/links', 'r24.payment.create' => '/payments', 'r24.transactions.read' => '/transactions',
            'r24.links.disable' => '/links/'.$id.'/disable', 'r24.widgets.create' => '/links/'.$id.'/widgets', 'r24.widgets.read' => '/widgets/'.$id,
            'r24.payment.read' => '/payments/'.$id, 'r24.payment.query' => '/payments/'.$id.'/query',
            'r24.ledger.post' => '/attempts/'.$id.'/post', 'r24.ledger.reconcile' => '/attempts/'.$id.'/reconcile',
            default => throw new LogicException('Unapproved payment scope'),
        };
        if (str_contains($suffix, $id) && $id !== '') {
            Identity::check((bool) preg_match('/^[a-f0-9-]{36}$/D', $id));
        }
        $resource = $base.$suffix;
        $command = str_ends_with($action, '.read') ? null : bin2hex(random_bytes(16));
    }
    if ($command !== null && isset($grants[$user->id.'|'.$action.'|'.$merchant])) {
        $previous = Identity::verified($grants[$user->id.'|'.$action.'|'.$merchant], $context['public']);
        StagingCommandState::assertNotUnresolved($previous);
    }
    $ttl = $operation === 'grant' ? filter_var($argv[6] ?? '600', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 600]]) : 600;
    Identity::check($ttl !== false);
    $envelope = $make($action, $resource, $command, $extra, $ttl);
    if (isset($event)) {
        saveFinancialJson($root.'/mock-event-'.$command.'.json', $event);
    }
    $grants[$user->id.'|'.$action.'|'.$merchant] = $envelope;
    saveFinancialJson($root.'/capabilities.json', $grants);
    echo Identity::encode(['action' => $action, 'resource' => $resource, 'capability' => $envelope['value']['id'], 'command' => $command]).PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, 'Fictional financial custody denied: '.$error::class.' at '.basename($error->getFile()).':'.$error->getLine().PHP_EOL);
    exit(1);
}
