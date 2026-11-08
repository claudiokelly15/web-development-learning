<?php

namespace Tests\Feature;

use App\Actions\Merchants\SubmissionEvidenceCipher as Cipher;
use App\Actions\Merchants\SubmissionEvidenceEnvironment;
use App\Actions\Merchants\VerificationEnvironment;
use Illuminate\Support\Str;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\FakeMerchantVerificationEnvironment;
use Tests\Support\FakeSubmissionEvidenceEnvironment;
use Tests\TestCase;

class SubmissionEvidenceCipherTest extends TestCase
{
    private FakeSubmissionEvidenceEnvironment $environment;

    private FakeMerchantVerificationEnvironment $verification;

    private array $context;

    private array $payload;

    protected function setUp(): void
    {
        parent::setUp();
        $this->environment = new FakeSubmissionEvidenceEnvironment;
        $this->verification = new FakeMerchantVerificationEnvironment;
        $this->app->instance(SubmissionEvidenceEnvironment::class, $this->environment);
        $this->app->instance(VerificationEnvironment::class, $this->verification);
        $this->context = ['application_id' => (string) Str::ulid(), 'merchant_id' => (string) Str::ulid(), 'contract_version' => 'submission-evidence-v1', 'actor_user_id' => 1, 'membership_id' => 1, 'auth_version' => 1, 'authority_version' => 1, 'email_binding_version' => 1, 'attempt_number' => 1, 'profile_version' => 1, 'context_version' => 1, 'identity_id' => (string) Str::ulid(), 'identity_version' => 1, 'contact_id' => (string) Str::ulid(), 'contact_version' => 1, 'purpose' => 'application_phone', 'proof_id' => (string) Str::ulid(), 'submission_request_id' => (string) Str::uuid(), 'submitted_at_ms' => 1794096000003, 'submitted_at' => '2026-11-08 00:00:00.003', 'audit_event_id' => (string) Str::ulid(), 'audit_event_type' => 'application.submitted', 'evidence_key_ref' => 'evidence.v1', 'binding_key_ref' => 'binding.v1', 'envelope_version' => 1, 'retention_policy_version' => 'fixture.retention'];
        $this->payload = ['v' => 1, 'email' => 'owner@fixture.test', 'email_verified_at' => '2026-11-08 00:00:00.000', 'business_name' => 'Fictional Merchant', 'market' => 'TZ', 'currency' => 'TZS', 'identity_content_mac' => $this->cipher()->commitment('identity', 'FIXTURE-ABCDEFGH', $this->context), 'contact_content_mac' => $this->cipher()->commitment('contact', '+255712345678', $this->context)];
    }

    private function cipher(): Cipher
    {
        return app(Cipher::class);
    }

    private function denied(\Closure $operation): void
    {
        try {
            $operation();
            $this->fail('Evidence crypto must fail closed.');
        } catch (LogicException $error) {
            $this->assertSame('Submission evidence unavailable.', $error->getMessage());
            $this->assertStringNotContainsString('FIXTURE-ABCDEFGH', $error->getTraceAsString());
            $this->assertStringNotContainsString('owner@fixture.test', $error->getTraceAsString());
        }
    }

    /** Independent wire encoder: no application encrypt/decrypt/AAD helper used. */
    private function rawEnvelope(string $plain): string
    {
        $context = $this->context;
        $parts = ['xlipa-submission-evidence-v1', 'fixture', 'xchacha20poly1305-ietf', '1', $context['evidence_key_ref'], $context['binding_key_ref']];
        foreach (Cipher::FIELDS as $field) {
            $value = (string) $context[$field];
            $parts[] = in_array($field, ['application_id', 'merchant_id', 'identity_id', 'contact_id', 'proof_id', 'audit_event_id'], true) ? strtoupper($value) : $value;
        }
        $aad = implode('', array_map(fn (string $part): string => pack('N', strlen($part)).$part, $parts));
        $nonce = random_bytes(24);
        $ct = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($plain, $aad, $nonce, $this->environment->keys[$context['evidence_key_ref']]);
        $encode = fn (string $value): string => rtrim(strtr(base64_encode($value), '+/', '-_'), '=');

        return json_encode(['v' => 1, 'alg' => 'xchacha20poly1305-ietf', 'kid' => $context['evidence_key_ref'], 'binding_kid' => $context['binding_key_ref'], 'nonce' => $encode($nonce), 'ct' => $encode($ct)], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public function test_round_trip_random_nonces_and_independent_sodium_interoperability(): void
    {
        $one = $this->cipher()->encrypt($this->context, $this->payload);
        $two = $this->cipher()->encrypt($this->context, $this->payload);
        $this->assertNotSame($one, $two);
        $this->assertSame($this->payload, $this->cipher()->decrypt($this->context, $one));
        $plain = json_encode($this->payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $this->assertSame($this->payload, $this->cipher()->decrypt($this->context, $this->rawEnvelope($plain)));
        foreach (['owner@fixture.test', 'FIXTURE-ABCDEFGH', '+255712345678'] as $sensitive) {
            $this->assertStringNotContainsString($sensitive, $one);
        }
        $this->assertTrue($this->cipher()->matchesContents($this->context, $this->payload, 'FIXTURE-ABCDEFGH', '+255712345678'));
        $this->assertFalse($this->cipher()->matchesContents($this->context, $this->payload, 'FIXTURE-CHANGED1', '+255712345678'));
        $this->assertFalse($this->cipher()->matchesContents($this->context, $this->payload, 'FIXTURE-ABCDEFGH', '+255712345679'));
    }

    public static function contextChanges(): array
    {
        return [['merchant_id'], ['actor_user_id'], ['application_id'], ['identity_id'], ['contact_id'], ['profile_version'], ['email_binding_version'], ['authority_version'], ['auth_version'], ['context_version'], ['identity_version'], ['contact_version'], ['attempt_number'], ['membership_id'], ['proof_id'], ['audit_event_id'], ['submission_request_id'], ['purpose']];
    }

    #[DataProvider('contextChanges')]
    public function test_bound_context_substitution_is_denied(string $field): void
    {
        $envelope = $this->cipher()->encrypt($this->context, $this->payload);
        $context = $this->context;
        $context[$field] = is_int($context[$field]) ? $context[$field] + 1 : (str_ends_with($field, '_id') && $field !== 'submission_request_id' ? (string) Str::ulid() : ($field === 'submission_request_id' ? (string) Str::uuid() : 'identity_declaration'));
        $this->denied(fn () => $this->cipher()->decrypt($context, $envelope));
    }

    public static function malformed(): array
    {
        return [['invalid_json'], ['extra_field'], ['wrong_algorithm'], ['padded_nonce'], ['tampered_ct'], ['duplicate_key'], ['whitespace'], ['wrong_key']];
    }

    #[DataProvider('malformed')]
    public function test_malformed_or_tampered_envelope_is_denied(string $kind): void
    {
        $envelope = $this->cipher()->encrypt($this->context, $this->payload);
        $fields = json_decode($envelope, true);
        if ($kind === 'invalid_json') {
            $envelope = '{';
        } elseif ($kind === 'whitespace') {
            $envelope = ' '.$envelope;
        } elseif ($kind === 'duplicate_key') {
            $envelope = str_replace('"v":1', '"v":1,"v":1', $envelope);
        } elseif ($kind === 'wrong_key') {
            $this->environment->keys['evidence.v1'] = random_bytes(32);
        } else {
            match ($kind) {
                'extra_field' => $fields['nin'] = 'DO-NOT-STORE',
                'wrong_algorithm' => $fields['alg'] = 'aes',
                'padded_nonce' => $fields['nonce'] .= '=',
                'tampered_ct' => $fields['ct'][0] = $fields['ct'][0] === 'A' ? 'B' : 'A',
            };
            $envelope = json_encode($fields, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        }
        $this->denied(fn () => $this->cipher()->decrypt($this->context, $envelope));
    }

    public function test_authenticated_but_noncanonical_payload_and_unknown_sensitive_fields_are_denied(): void
    {
        $this->denied(fn () => $this->cipher()->encrypt($this->context, [...$this->payload, 'nin' => 'DO-NOT-STORE']));
        $this->denied(fn () => $this->cipher()->decrypt($this->context, $this->rawEnvelope(json_encode($this->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))));
        $bad = $this->payload;
        $bad['v'] = 1.0;
        $this->denied(fn () => $this->cipher()->encrypt($this->context, $bad));
        $bad = $this->payload;
        $bad['email_verified_at'] = '2026-02-30 00:00:00.000';
        $this->denied(fn () => $this->cipher()->encrypt($this->context, $bad));
    }

    public static function aliases(): array
    {
        return [['binding'], ['phone'], ['nin'], ['otp'], ['contact'], ['app']];
    }

    #[DataProvider('aliases')]
    public function test_key_aliasing_is_denied(string $domain): void
    {
        $this->environment->keys['evidence.v1'] = match ($domain) {
            'binding' => $this->environment->keys['binding.v1'],
            'app' => app('encrypter')->getKey(),
            default => $this->verification->keys[$domain],
        };
        $this->denied(fn () => $this->cipher()->encrypt($this->context, $this->payload));
    }

    public function test_default_dependencies_restore_time_retention_and_retired_keys_fail_closed(): void
    {
        $default = new Cipher(new SubmissionEvidenceEnvironment, $this->verification);
        $this->denied(fn () => $default->encrypt($this->context, $this->payload));
        $this->environment->restore = false;
        $this->denied(fn () => $this->cipher()->encrypt($this->context, $this->payload));
        $this->environment->restore = true;
        $this->environment->retention = false;
        $this->denied(fn () => $this->cipher()->encrypt($this->context, $this->payload));
        $this->environment->retention = true;
        $this->environment->time = 1;
        $this->denied(fn () => $this->cipher()->encrypt($this->context, $this->payload));
        $this->environment->time = 1794096000100;
        $this->context['evidence_key_ref'] = 'evidence.old';
        $envelope = $this->rawEnvelope(json_encode($this->payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        $this->assertSame($this->payload, $this->cipher()->decrypt($this->context, $envelope));
        $this->denied(fn () => $this->cipher()->encrypt($this->context, $this->payload));
        unset($this->environment->keys['evidence.old']);
        $this->denied(fn () => $this->cipher()->decrypt($this->context, $envelope));
    }
}
