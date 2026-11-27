<?php

// OS-custodian CLI only; never exposed by the web router.
require __DIR__.'/../backend/vendor/autoload.php';

use App\Actions\Auth\RegisterMerchantOwner;
use App\Actions\Merchants\BusinessNameCompletionHttpInput;
use App\Actions\Merchants\LifecycleMutation;
use App\Actions\Merchants\WorkflowSchemaReadiness;
use App\Actions\Payments\PaymentStore;
use App\Models\User;
use App\Staging\FinancialProvisioner;
use App\Staging\FinancialSchema;
use App\Staging\StagingCommandState;
use App\Staging\StagingIdentity as Identity;
use App\Staging\StagingRuntime;
use App\Staging\StagingWorkflowAuthority;
use App\Staging\WorkflowProvisioner;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

Identity::check(PHP_SAPI === 'cli');

function saveJson(string $path, array $data): void
{
    $temporary = $path.'.new';
    Identity::check(! file_exists($temporary) && ! is_link($path));
    file_put_contents($temporary, Identity::encode($data), LOCK_EX);
    Identity::check(rename($temporary, $path));
}

try {
    $operation = $argv[1] ?? '';
    $anchor = Identity::anchor();
    if ($operation === 'provision') {
        if (! is_dir($anchor)) {
            Identity::path(str_replace('\\', '/', dirname($anchor)));
            mkdir($anchor, 0700);
        }
        Identity::path($anchor);
        if (! file_exists($anchor.'/issuer-public.json')) {
            Identity::check(! file_exists($anchor.'/issuer-secret.json'));
            $pair = sodium_crypto_sign_keypair();
            $issuer = 'fictional-local-custodian-'.bin2hex(random_bytes(8));
            saveJson($anchor.'/issuer-secret.json', ['secret' => base64_encode(sodium_crypto_sign_secretkey($pair))]);
            saveJson($anchor.'/issuer-public.json', ['issuer' => $issuer, 'public' => base64_encode(sodium_crypto_sign_publickey($pair))]);
        }
        $id = 'run-'.bin2hex(random_bytes(16));
        $root = $anchor.'/'.$id;
        mkdir($root, 0700);
        foreach (['storage', 'storage/framework', 'storage/framework/sessions', 'storage/framework/views', 'storage/framework/cache', 'storage/logs'] as $directory) {
            mkdir($root.'/'.$directory, 0700);
        }
        saveJson($root.'/owner.json', ['runtime' => $id, 'generation' => $id, 'namespace' => 'xlipa-fictional-r21']);
        saveJson($root.'/app-key.json', ['key' => 'base64:'.base64_encode(random_bytes(32))]);
        saveJson($root.'/capabilities.json', []);
        $headers = [];
        foreach (['workflow', 'financial', 'admission'] as $kind) {
            $path = $root.'/'.$kind.'.sqlite';
            Identity::check(! file_exists($path));
            touch($path);
            $pdo = new PDO('sqlite:'.Identity::path($path));
            $headers[$kind] = ['application' => random_int(1, 2147483647), 'version' => random_int(1, 2147483647)];
            $pdo->exec('PRAGMA application_id='.$headers[$kind]['application']);
            $pdo->exec('PRAGMA user_version='.$headers[$kind]['version']);
            if ($kind !== 'workflow') {
                $pdo->exec('CREATE TABLE staging_identity(runtime TEXT NOT NULL,generation TEXT NOT NULL,profile TEXT NOT NULL)');
                $pdo->prepare('INSERT INTO staging_identity VALUES(?,?,?)')->execute([$id, $id, $kind === 'financial' ? 'unprovisioned-r22' : 'admission-r21']);
            }
            if ($kind === 'admission') {
                $pdo->exec('CREATE TABLE revoked(id TEXT PRIMARY KEY); CREATE TABLE commands(id TEXT PRIMARY KEY,capability TEXT NOT NULL); CREATE TABLE clock(observed INTEGER NOT NULL)');
                $pdo->prepare('INSERT INTO clock VALUES(?)')->execute([time()]);
            }
            $pdo = null;
        }
        $trust = Identity::read($anchor.'/issuer-public.json');
        $context = ['namespace' => 'xlipa-fictional-r21', 'issuer' => $trust['issuer'], 'runtime' => $id,
            'generation' => $id, 'store' => bin2hex(random_bytes(16)), 'root' => $root,
            'origin' => Identity::ORIGIN, 'profile' => 'foundation', 'release' => Identity::release(),
            'issued' => time(), 'expires' => time() + 28800, 'headers' => $headers,
            'financial_program' => ($argv[2] ?? 'foundation') === 'payments' ? \App\Staging\StagingFinancialAuthority::VERSION : null];
        $app = StagingRuntime::application($context);
        $selection = Identity::read(str_replace('\\', '/', realpath(__DIR__.'/../backend/database/activation/b32c/selection-manifest.json')));
        foreach ([array_slice(array_keys($selection['foundation']), 0, 3), array_slice(array_keys($selection['foundation']), 3)] as $chunk) {
            Identity::check(Artisan::call('migrate', ['--database' => 'sqlite', '--path' => array_map(fn ($name) => 'database/migrations/'.$name.'.php', $chunk), '--no-interaction' => true, '--force' => true]) === 0);
        }
        Identity::check(app(WorkflowSchemaReadiness::class)->profile() === 'foundation');
        $secret = base64_decode(Identity::read($anchor.'/issuer-secret.json')['secret'], true);
        foreach (['workflow', 'financial', 'admission'] as $kind) {
            $pdo = new PDO('sqlite:'.$root.'/'.$kind.'.sqlite');
            $context['schemas'][$kind] = Identity::schema($pdo);
            $pdo = null;
        }
        if (in_array($argv[2] ?? 'foundation', ['onboarding', 'payments'], true)) {
            $keys = [];
            foreach (['phone', 'nin', 'otp', 'contact', 'evidence', 'binding'] as $domain) {
                $keys[$domain] = base64_encode(random_bytes(32));
            }
            saveJson($root.'/onboarding-keys.json', $keys);
            if (($argv[2] ?? '') === 'payments') {
                saveJson($root.'/r24-keys.json', ['mock' => base64_encode(random_bytes(32))]);
            }
            saveJson($root.'/workflow-provisioning.json', Identity::signed([...$context, 'purpose' => 'workflow-schema-provision', 'provision_profile' => WorkflowProvisioner::VERSION], $secret));
            WorkflowProvisioner::provision($id);
            $context['workflow_profile'] = WorkflowProvisioner::VERSION;
        } else {
            Identity::check(($argv[2] ?? 'foundation') === 'foundation');
        }
        foreach (['workflow', 'financial', 'admission'] as $kind) {
            $pdo = new PDO('sqlite:'.$root.'/'.$kind.'.sqlite');
            $context['schemas'][$kind] = Identity::schema($pdo);
            $pdo = null;
        }
        $secret = base64_decode(Identity::read($anchor.'/issuer-secret.json')['secret'], true);
        $context['financial_profile'] = FinancialSchema::VERSION;
        saveJson($root.'/provisioning.json', Identity::signed([...$context, 'purpose' => 'financial-schema-provision'], $secret));
        FinancialProvisioner::provision($id);
        $financial = new PDO('sqlite:'.Identity::path($root.'/financial.sqlite'));
        $context['schemas']['financial'] = Identity::schema($financial);
        $financial = null;
        saveJson($root.'/manifest.json', Identity::signed($context, $secret));
        saveJson($anchor.'/current.json', ['runtime' => $id]);
        echo Identity::encode(['runtime' => $id, 'origin' => Identity::ORIGIN]).PHP_EOL;
        exit(0);
    }

    $id = $argv[2] ?? Identity::read($anchor.'/current.json')['runtime'];
    if ($operation === 'financial-provision') {
        echo Identity::encode(FinancialProvisioner::provision($id)).PHP_EOL;
        exit(0);
    }
    if ($operation === 'retire') {
        $context = Identity::retirement($id);
        $root = $context['root'];
        Identity::check(file_put_contents($root.'/retired', 'retired fictional generation', LOCK_EX) !== false);
        echo "Retired; stores and evidence retained. New provision required.\n";
        exit(0);
    }
    $context = Identity::load($id);
    $root = $context['root'];
    if ($operation === 'revoke') {
        $capability = $argv[3] ?? '';
        Identity::check((bool) preg_match('/^[a-f0-9]{32}$/D', $capability));
        $pdo = new PDO('sqlite:'.$root.'/admission.sqlite');
        $pdo->prepare('INSERT OR IGNORE INTO revoked(id) VALUES(?)')->execute([$capability]);
        echo "Revoked fictional capability.\n";
        exit(0);
    }
    $app = StagingRuntime::application($context);
    if ($operation === 'financial-status') {
        $pdo = new PDO('sqlite:'.Identity::path($root.'/financial.sqlite'));
        $pdo->exec('PRAGMA foreign_keys=ON; PRAGMA recursive_triggers=ON');
        echo Identity::encode(FinancialSchema::assertReady($pdo, $context)).PHP_EOL;
        exit(0);
    }
    if ($operation === 'safety') {
        $denied = [];
        foreach (['provider', 'webhook', 'otp'] as $purpose) {
            try {
                Http::post('https://'.$purpose.'.example.test', []);
                throw new LogicException('Unexpected transport success');
            } catch (LogicException $error) {
                Identity::check($error->getMessage() === 'External communication is denied in staging.');
                $denied[] = $purpose;
            }
        }
        Identity::check(config('mail.default') === 'array' && array_keys(config('mail.mailers')) === ['array']);
        Identity::check(! app()->bound(PaymentStore::class));
        Identity::check(! app()->bound('p10.chief-context'));
        echo Identity::encode(['denied' => $denied, 'mail' => 'array-only', 'financial' => 'unbound', 'identity' => 'unbound']).PHP_EOL;
        exit(0);
    }
    if ($operation === 'account') {
        $email = $argv[3] ?? '';
        $role = $argv[4] ?? 'merchant';
        Identity::check((bool) preg_match('/^[a-z0-9-]+@example\.test$/D', $email) && in_array($role, ['merchant', 'chief_admin', 'admin'], true));
        Identity::check(! User::where('email', $email)->exists());
        $password = 'Fictional-'.bin2hex(random_bytes(14));
        if ($role === 'merchant') {
            app(RegisterMerchantOwner::class)->execute(['fullName' => 'Fictional Staging Merchant', 'businessName' => '', 'email' => $email, 'phone' => '+255600000000', 'password' => $password, 'terms' => '1']);
        } else {
            DB::transaction(function () use ($email, $role, $password): void {
                $user = User::create(['name' => 'Fictional Staging Administrator', 'email' => $email, 'password' => $password]);
                DB::table('platform_role_assignments')->insert(['user_id' => $user->id, 'role' => $role, 'granted_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            });
        }
        $user = User::where('email', $email)->firstOrFail();
        $merchant = DB::table('merchant_memberships')->where('user_id', $user->id)->value('merchant_id');
        // Private, ignored local handoff only. No passwords printed in logs or HTTP.
        saveJson($root.'/account-'.$user->id.'.json', ['email' => $email, 'password' => $password, 'actor' => $user->id, 'merchant' => $merchant]);
        $action = match ($role) {
            'merchant' => 'dashboard.read', 'chief_admin' => 'admin.chief.read', 'admin' => 'admin.staff.read'
        };
        $resource = $role === 'merchant' ? '/merchants/'.$merchant.'/dashboard' : ($role === 'chief_admin' ? '/admin/chief' : '/admin/staff');
        $args = [$user, $merchant, $action, $resource, null];
    } elseif ($operation === 'verify-link' || $operation === 'reset-token') {
        $user = User::where('email', $argv[3] ?? '')->firstOrFail();
        Identity::check(str_ends_with($user->email, '@example.test'));
        if ($operation === 'verify-link') {
            $parameters = ['id' => $user->id, 'hash' => sha1($user->email)];
            if (app(LifecycleMutation::class)->versioned()) {
                $parameters['binding_version'] = $user->email_binding_version;
            }
            echo URL::temporarySignedRoute('verification.verify', now()->addMinutes(10), $parameters).PHP_EOL;
        } else {
            // Private local-only handoff for the existing foundation broker, never real email.
            saveJson($root.'/reset-'.$user->id.'.json', ['token' => Password::createToken($user)]);
            echo "Fictional reset token captured privately.\n";
        }
        exit(0);
    } elseif (in_array($operation, ['suspend', 'invalidate', 'revoke-membership'], true)) {
        $user = User::where('email', $argv[3] ?? '')->firstOrFail();
        Identity::check(str_ends_with($user->email, '@example.test'));
        app(LifecycleMutation::class)->run(function () use ($user, $operation): void {
            if ($operation === 'revoke-membership') {
                DB::table('merchant_memberships')->where('user_id', $user->id)->update(['status' => 'revoked', 'revoked_at' => now()]);
            } else {
                $changes = ['auth_version' => LifecycleMutation::next($user->auth_version)];
                if ($operation === 'suspend') {
                    $changes['account_status'] = 'suspended';
                }
                DB::table('users')->where('id', $user->id)->update($changes);
            }
        });
        echo "Fictional account authority invalidated.\n";
        exit(0);
    } elseif ($operation === 'grant-workflow') {
        Identity::check(($context['workflow_profile'] ?? null) === WorkflowProvisioner::VERSION);
        $user = User::where('email', $argv[3] ?? '')->firstOrFail();
        $action = $argv[4] ?? '';
        $membership = DB::table('merchant_memberships')->where('user_id', $user->id)->where('role', 'owner')->where('status', 'active')->whereNull('revoked_at')->sole();
        $merchant = $membership->merchant_id;
        $suffix = match ($action) {
            'onboarding.verification.read' => '/verification', 'onboarding.contact' => '/verification/contact', 'onboarding.identity' => '/verification/identity',
            'onboarding.otp.request' => '/verification/request', 'onboarding.otp.verify' => '/verification/code', 'onboarding.application.read' => '/applications/integrated',
            default => throw new LogicException('Unknown workflow scope'),
        };
        $extra = ['membership_id' => $membership->id, 'authority_version' => $membership->authority_version, 'email_version' => $user->email_binding_version];
        $args = [$user, $merchant, $action, '/merchants/'.$merchant.$suffix, str_ends_with($action, '.read') ? null : bin2hex(random_bytes(16))];
    } elseif ($operation === 'prepare-submission-resolution') {
        Identity::check(($context['workflow_profile'] ?? null) === WorkflowProvisioner::VERSION);
        $user = User::where('email', $argv[3] ?? '')->firstOrFail();
        $merchant = DB::table('merchant_memberships')->where('user_id', $user->id)->where('role', 'owner')->where('status', 'active')->whereNull('revoked_at')->sole()->merchant_id;
        $grants = Identity::read($root.'/capabilities.json');
        $previous = Identity::verified($grants[$user->id.'|onboarding.submit|'.$merchant], $context['public']);
        Identity::check($previous['actor'] === $user->id && $previous['merchant'] === $merchant && $previous['runtime'] === $id);
        $extra = ['expected' => $previous['expected']];
        $args = [$user, $merchant, 'onboarding.submit.resolve', '/merchants/'.$merchant.'/applications/integrated/resolve', bin2hex(random_bytes(16))];
    } elseif ($operation === 'prepare-submission') {
        $user = User::where('email', $argv[3] ?? '')->firstOrFail();
        $member = DB::table('merchant_memberships')->where('user_id', $user->id)->where('role', 'owner')->where('status', 'active')->whereNull('revoked_at')->sole();
        $merchant = $member->merchant_id;
        $grants = Identity::read($root.'/capabilities.json');
        if (isset($grants[$user->id.'|onboarding.submit|'.$merchant])) {
            StagingCommandState::assertNotUnresolved(Identity::verified($grants[$user->id.'|onboarding.submit|'.$merchant], $context['public']));
        }
        $application = DB::table('merchant_applications')->where('merchant_id', $merchant)->where('current_slot', 1)->sole();
        $onboarding = DB::table('merchant_onboarding_contexts')->where('merchant_id', $merchant)->sole();
        $identity = DB::table('merchant_identity_records')->where('id', $onboarding->current_identity_id)->sole();
        $contact = DB::table('merchant_contact_versions')->where('id', $onboarding->current_contact_id)->sole();
        $proof = DB::table('contact_verification_proofs')->where('contact_id', $contact->id)->where('state', 'available')->sole();
        $expected = ['application_id' => $application->id, 'membership_id' => $member->id, 'auth_version' => $user->auth_version,
            'email_binding_version' => $user->email_binding_version, 'authority_version' => $member->authority_version, 'attempt_number' => $application->attempt_number,
            'profile_version' => $application->profile_version, 'context_version' => $onboarding->context_version, 'identity_id' => $identity->id, 'identity_version' => $identity->version,
            'contact_id' => $contact->id, 'contact_version' => $contact->version, 'proof_id' => $proof->id, 'submission_request_id' => (string) Str::uuid()];
        $extra = ['expected' => $expected];
        $args = [$user, $merchant, 'onboarding.submit', '/merchants/'.$merchant.'/applications/integrated/submit', bin2hex(random_bytes(16))];
    } elseif ($operation === 'attest-proof') {
        $user = User::where('email', $argv[3] ?? '')->firstOrFail();
        $merchant = DB::table('merchant_memberships')->where('user_id', $user->id)->where('role', 'owner')->where('status', 'active')->whereNull('revoked_at')->sole()->merchant_id;
        $onboarding = DB::table('merchant_onboarding_contexts')->where('merchant_id', $merchant)->sole();
        $proof = (array) DB::table('contact_verification_proofs')->where('merchant_id', $merchant)->where('actor_user_id', $user->id)->where('contact_id', $onboarding->current_contact_id)->where('state', 'available')->sole();
        $challenge = (array) DB::table('phone_otp_challenges')->where('id', $proof['challenge_id'])->sole();
        $intent = (array) DB::table('otp_delivery_intents')->where('challenge_id', $challenge['id'])->sole();
        $identity = (array) DB::table('merchant_identity_records')->where('id', $onboarding->current_identity_id)->sole();
        Identity::check($proof['provenance'] === 'simulated' && $challenge['transport_mode'] === 'simulated' && $intent['transport_mode'] === 'simulated' && $challenge['state'] === 'consumed' && $intent['state'] === 'accepted');
        $extra = ['lineage' => StagingWorkflowAuthority::lineage($proof, $challenge, $intent, $identity)];
        $saveProof = $proof['id'];
        $args = [$user, $merchant, 'onboarding.proof.attest', $proof['id'], null];
    } elseif ($operation === 'prepare-decision') {
        $user = User::where('email', $argv[3] ?? '')->firstOrFail();
        $application = DB::table('merchant_applications')->where('id', $argv[4] ?? '')->sole();
        $grants = Identity::read($root.'/capabilities.json');
        if (isset($grants[$user->id.'|onboarding.decision|'.$application->merchant_id])) {
            StagingCommandState::assertNotUnresolved(Identity::verified($grants[$user->id.'|onboarding.decision|'.$application->merchant_id], $context['public']));
        }
        $role = DB::table('platform_role_assignments')->where('user_id', $user->id)->where('role', 'chief_admin')->whereNull('revoked_at')->sole();
        $evidence = DB::table('merchant_application_submission_evidence')->where('application_id', $application->id)->sole();
        $outcome = $argv[5] ?? '';
        $reason = $argv[6] ?? null;
        Identity::check(in_array($outcome, ['approved', 'rejected'], true) && ($outcome === 'approved' ? $reason === null : is_string($reason) && preg_match('/^[a-z][a-z0-9_]{0,49}$/D', $reason)));
        $expected = ['attempt_number' => $application->attempt_number, 'profile_version' => $application->profile_version,
            'reviewer_auth_version' => $user->auth_version, 'reviewer_email_binding_version' => $user->email_binding_version,
            'reviewer_authority_version' => $role->user_id, 'submission_request_id' => $evidence->submission_request_id, 'decision_request_id' => (string) Str::uuid()];
        $extra = ['application' => $application->id, 'expected' => $expected, 'outcome' => $outcome, 'reason' => $reason];
        $args = [$user, $application->merchant_id, 'onboarding.decision', '/admin/applications/'.$application->id.'/decision', bin2hex(random_bytes(16))];
    } elseif ($operation === 'assure-chief' || $operation === 'grant-decision-read') {
        $user = User::where('email', $argv[3] ?? '')->firstOrFail();
        $application = DB::table('merchant_applications')->where('id', $argv[4] ?? '')->sole();
        $role = (array) DB::table('platform_role_assignments')->where('user_id', $user->id)->where('role', 'chief_admin')->whereNull('revoked_at')->sole();
        if ($operation === 'assure-chief') {
            $grants = Identity::read($root.'/capabilities.json');
            $decision = Identity::verified($grants[$user->id.'|onboarding.decision|'.$application->merchant_id], $context['public']);
            $extra = ['command_id' => $decision['command'], 'authority' => hash('sha256', Identity::encode($role))];
            $saveAssurance = true;
            $args = [$user, $application->merchant_id, 'onboarding.chief.assurance', $application->id, null];
        } else {
            $args = [$user, $application->merchant_id, 'onboarding.decision.read', '/admin/applications/'.$application->id.'/decision', null];
        }
    } elseif ($operation === 'prepare-name-resolution') {
        Identity::check(($context['workflow_profile'] ?? null) === WorkflowProvisioner::VERSION);
        $user = User::where('email', $argv[3] ?? '')->firstOrFail();
        $merchant = DB::table('merchant_memberships')->where('user_id', $user->id)->where('role', 'owner')->where('status', 'active')->whereNull('revoked_at')->sole()->merchant_id;
        $grants = Identity::read($root.'/capabilities.json');
        $previous = Identity::verified($grants[$user->id.'|onboarding.name|'.$merchant], $context['public']);
        Identity::check($previous['actor'] === $user->id && $previous['merchant'] === $merchant && $previous['runtime'] === $id);
        $extra = ['name' => $previous['name'], 'expected' => $previous['expected']];
        $args = [$user, $merchant, 'onboarding.name.resolve', '/merchants/'.$merchant.'/onboarding/complete/resolve', bin2hex(random_bytes(16))];
    } elseif ($operation === 'prepare-name') {
        Identity::check(($context['workflow_profile'] ?? null) === WorkflowProvisioner::VERSION);
        $user = User::where('email', $argv[3] ?? '')->firstOrFail();
        $membership = DB::table('merchant_memberships')->where('user_id', $user->id)->where('role', 'owner')->where('status', 'active')->whereNull('revoked_at')->sole();
        $business = DB::table('merchants')->where('id', $membership->merchant_id)->sole();
        $grants = Identity::read($root.'/capabilities.json');
        if (isset($grants[$user->id.'|onboarding.name|'.$business->id])) {
            StagingCommandState::assertNotUnresolved(Identity::verified($grants[$user->id.'|onboarding.name|'.$business->id], $context['public']));
        }
        $application = DB::table('merchant_applications')->where('merchant_id', $business->id)->where('current_slot', 1)->sole();
        Identity::check($application->status === 'draft' && $business->business_name === '' && $user->email_verified_at !== null);
        $name = (new BusinessNameCompletionHttpInput)->name($argv[4] ?? '');
        $expected = ['application_id' => $application->id, 'attempt_number' => $application->attempt_number, 'merchant_profile_version' => $business->profile_version,
            'draft_profile_version' => $application->profile_version, 'membership_id' => $membership->id, 'authority_version' => $membership->authority_version,
            'auth_version' => $user->auth_version, 'email_binding_version' => $user->email_binding_version, 'completion_request_id' => (string) Str::uuid()];
        $extra = ['name' => $name, 'expected' => $expected];
        $args = [$user, $business->id, 'onboarding.name', '/merchants/'.$business->id.'/onboarding/complete', bin2hex(random_bytes(16))];
    } elseif ($operation === 'grant-probe') {
        $user = User::where('email', $argv[3] ?? '')->firstOrFail();
        $command = $argv[4] ?? null;
        $args = [$user, null, 'foundation.probe', $command === null ? '/staging/admission' : '/staging/command', $command];
    } else {
        throw new LogicException('Unknown staging control operation.');
    }
    [$user, $merchant, $action, $resource, $command] = $args;
    $grant = array_intersect_key($context, array_flip(['namespace', 'issuer', 'runtime', 'release', 'generation', 'store', 'issued', 'expires']));
    $grant = [...$grant, 'id' => bin2hex(random_bytes(16)), 'actor' => (int) $user->id, 'merchant' => $merchant,
        'action' => $action, 'resource' => $resource, 'version' => $user->auth_version, 'command' => $command];
    $grant = [...$grant, ...($extra ?? [])];
    if (str_starts_with($action, 'onboarding.')) {
        $grant['issued'] = time();
        $grant['expires'] = min(time() + 600, $context['expires']);
    }
    $secret = base64_decode(Identity::read($anchor.'/issuer-secret.json')['secret'], true);
    $grants = Identity::read($root.'/capabilities.json');
    $grants[$user->id.'|'.$action.'|'.($merchant ?? '')] = Identity::signed($grant, $secret);
    saveJson($root.'/capabilities.json', $grants);
    if (isset($saveProof)) {
        saveJson($root.'/proof-'.$saveProof.'.json', Identity::signed($grant, $secret));
    }
    if (isset($saveAssurance)) {
        $grant['expires'] = min(time() + 120, $context['expires']);
        saveJson($root.'/chief-assurance-'.$user->id.'.json', Identity::signed($grant, $secret));
    }
    echo Identity::encode(['actor' => $user->id, 'merchant' => $merchant, 'capability' => $grant['id'], 'action' => $action]).PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, 'Staging control denied: '.$error::class.' at '.basename($error->getFile()).':'.$error->getLine().PHP_EOL);
    foreach (array_slice($error->getTrace(), 0, 4) as $frame) {
        fwrite(STDERR, basename($frame['file'] ?? '').':'.($frame['line'] ?? 0).PHP_EOL);
    }
    exit(1);
}
