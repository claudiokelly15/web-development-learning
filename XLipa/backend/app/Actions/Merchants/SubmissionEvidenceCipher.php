<?php

namespace App\Actions\Merchants;

use DateTimeImmutable;
use DateTimeZone;
use LogicException;
use Throwable;

/** Internal SE-1.0 capsule crypto only. Never persists, submits, delivers or grants identity authority. */
final class SubmissionEvidenceCipher
{
    public const FIELDS = ['application_id', 'merchant_id', 'contract_version', 'actor_user_id', 'membership_id', 'auth_version', 'authority_version', 'email_binding_version', 'attempt_number', 'profile_version', 'context_version', 'identity_id', 'identity_version', 'contact_id', 'contact_version', 'purpose', 'proof_id', 'submission_request_id', 'submitted_at_ms', 'submitted_at', 'audit_event_id', 'audit_event_type', 'evidence_key_ref', 'binding_key_ref', 'envelope_version', 'retention_policy_version'];

    public const PAYLOAD = ['v', 'email', 'email_verified_at', 'business_name', 'market', 'currency', 'identity_content_mac', 'contact_content_mac'];

    private const FLAGS = JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

    public function __construct(private readonly SubmissionEvidenceEnvironment $environment, private readonly VerificationEnvironment $verification) {}

    private function fail(): never
    {
        throw new LogicException('Submission evidence unavailable.');
    }

    private function context(array $context): void
    {
        if (count($context) !== count(self::FIELDS) || array_diff(array_keys($context), self::FIELDS)) {
            $this->fail();
        }
        $ids = ['application_id', 'merchant_id', 'identity_id', 'contact_id', 'proof_id', 'audit_event_id'];
        $integers = ['actor_user_id', 'membership_id', 'auth_version', 'authority_version', 'email_binding_version', 'attempt_number', 'profile_version', 'context_version', 'identity_version', 'contact_version', 'submitted_at_ms', 'envelope_version'];
        foreach ($context as $field => $value) {
            if (in_array($field, $integers, true)) {
                if (! is_int($value) || $value < ($field === 'submitted_at_ms' ? 0 : 1)) {
                    $this->fail();
                }
            } elseif (! is_string($value) || str_contains($value, "\0")) {
                $this->fail();
            }
            if (in_array($field, $ids, true) && ! preg_match('/^[0-7][0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{25}$/D', $value)) {
                $this->fail();
            }
        }
        foreach (['evidence_key_ref', 'binding_key_ref', 'retention_policy_version'] as $field) {
            if (! preg_match('/^[a-z0-9._-]{1,'.($field === 'retention_policy_version' ? 50 : 64).'}$/D', $context[$field])) {
                $this->fail();
            }
        }
        if ($context['contract_version'] !== 'submission-evidence-v1' || $context['purpose'] !== 'application_phone' || $context['audit_event_type'] !== 'application.submitted' || $context['envelope_version'] !== 1 || $context['submitted_at_ms'] > 253402300799999 || $context['submitted_at'] !== VerificationAuditReconciler::occurredAt($context['submitted_at_ms']) || ! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/D', $context['submission_request_id'])) {
            $this->fail();
        }
    }

    /** Returns only the two dedicated keys; checks resolved domains and every configured APP_KEY version for aliasing. */
    private function keys(array $context, string $use): array
    {
        $this->context($context);
        if (! $this->environment->authorized() || ! $this->environment->restoreReady() || ! preg_match('/^[a-z0-9._-]{1,64}$/D', $this->environment->namespace()) || $this->environment->milliseconds() < $context['submitted_at_ms'] || ! $this->environment->retentionAllowed($context['retention_policy_version'], $use, $context['submitted_at_ms'])) {
            $this->fail();
        }
        if ($use === 'encrypt' && ($context['evidence_key_ref'] !== $this->environment->keyId('evidence') || $context['binding_key_ref'] !== $this->environment->keyId('binding'))) {
            $this->fail();
        }
        $encryption = $this->environment->key('evidence', $context['evidence_key_ref'], $use);
        $binding = $this->environment->key('binding', $context['binding_key_ref'], $use);
        $resolved = [$encryption, $binding];
        foreach (['phone', 'nin', 'otp', 'contact'] as $domain) {
            $resolved[] = $this->verification->key($domain, $this->verification->keyId($domain));
        }
        $hashes = [];
        foreach ($resolved as $key) {
            if (strlen($key) !== 32) {
                $this->fail();
            }
            $hashes[] = hash('sha256', $key);
        }
        if (count(array_unique($hashes)) !== count($hashes)) {
            $this->fail();
        }
        foreach (app('encrypter')->getAllKeys() as $key) {
            if (in_array(hash('sha256', $key), $hashes, true)) {
                $this->fail();
            }
        }

        return [$encryption, $binding];
    }

    private function aad(array $context): string
    {
        $parts = ['xlipa-submission-evidence-v1', $this->environment->namespace(), 'xchacha20poly1305-ietf', '1', $context['evidence_key_ref'], $context['binding_key_ref']];
        foreach (self::FIELDS as $field) {
            $value = (string) $context[$field];
            $parts[] = in_array($field, ['application_id', 'merchant_id', 'identity_id', 'contact_id', 'proof_id', 'audit_event_id'], true) ? strtoupper($value) : $value;
        }

        return VerificationCipher::lp(...$parts);
    }

    private function payload(array $payload, array $context): string
    {
        if (array_keys($payload) !== self::PAYLOAD || $payload['v'] !== 1 || $payload['market'] !== 'TZ' || $payload['currency'] !== 'TZS') {
            $this->fail();
        }
        foreach (array_slice(self::PAYLOAD, 1) as $field) {
            if (! is_string($payload[$field]) || preg_match('/[\x00-\x1f\x7f]/', $payload[$field])) {
                $this->fail();
            }
        }
        if (strlen($payload['email']) > 254 || strtolower(trim($payload['email'])) !== $payload['email'] || ! filter_var($payload['email'], FILTER_VALIDATE_EMAIL) || ! preg_match('/^[\x20-\x7e]+$/D', $payload['email']) || trim($payload['business_name']) !== $payload['business_name'] || mb_strlen($payload['business_name']) < 1 || mb_strlen($payload['business_name']) > 200) {
            $this->fail();
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s.v', $payload['email_verified_at'], new DateTimeZone('UTC'));
        if (! $date || $date->format('Y-m-d H:i:s.v') !== $payload['email_verified_at'] || $payload['email_verified_at'] > $context['submitted_at']) {
            $this->fail();
        }
        foreach (['identity_content_mac', 'contact_content_mac'] as $field) {
            if (! preg_match('/^[0-9a-f]{64}$/D', $payload[$field])) {
                $this->fail();
            }
        }
        $json = json_encode($payload, self::FLAGS);
        if (strlen($json) > 4096) {
            $this->fail();
        }

        return $json;
    }

    private function mac(string $domain, #[\SensitiveParameter] string $logical, array $context, string $key): string
    {
        if (! in_array($domain, ['identity', 'contact'], true) || strlen($logical) < 1 || strlen($logical) > 256 || preg_match('/[\x00-\x1f\x7f]/', $logical) || ($domain === 'contact' && ! preg_match('/^\+255[67][0-9]{8}$/D', $logical))) {
            $this->fail();
        }

        return hash_hmac('sha256', VerificationCipher::lp('xlipa-submission-content-v1', $domain, strtoupper($context['merchant_id']), strtoupper($context[$domain.'_id']), (string) $context['actor_user_id'], (string) $context[$domain.'_version'], $logical), $key);
    }

    public function commitment(string $domain, #[\SensitiveParameter] string $logical, array $context): string
    {
        try {
            [, $binding] = $this->keys($context, 'encrypt');

            return $this->mac($domain, $logical, $context, $binding);
        } catch (Throwable) {
            $this->fail();
        }
    }

    public function encrypt(array $context, #[\SensitiveParameter] array $payload): string
    {
        try {
            [$key] = $this->keys($context, 'encrypt');
            $plaintext = $this->payload($payload, $context);
            $nonce = random_bytes(24);
            $ciphertext = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($plaintext, $this->aad($context), $nonce, $key);

            return json_encode(['v' => 1, 'alg' => 'xchacha20poly1305-ietf', 'kid' => $context['evidence_key_ref'], 'binding_kid' => $context['binding_key_ref'], 'nonce' => $this->encode($nonce), 'ct' => $this->encode($ciphertext)], self::FLAGS);
        } catch (Throwable) {
            $this->fail();
        }
    }

    /** Internal protected capsule read; no route, accessor, job or public serializer. */
    public function decrypt(array $context, #[\SensitiveParameter] string $envelope): array
    {
        try {
            [$key] = $this->keys($context, 'decrypt');
            if (strlen($envelope) > 16384) {
                $this->fail();
            }
            $f = json_decode($envelope, true, 8, JSON_THROW_ON_ERROR);
            if (! is_array($f) || array_keys($f) !== ['v', 'alg', 'kid', 'binding_kid', 'nonce', 'ct'] || $f['v'] !== 1 || $f['alg'] !== 'xchacha20poly1305-ietf' || $f['kid'] !== $context['evidence_key_ref'] || $f['binding_kid'] !== $context['binding_key_ref'] || ! is_string($f['nonce']) || ! is_string($f['ct']) || json_encode($f, self::FLAGS) !== $envelope) {
                $this->fail();
            }
            $nonce = $this->decode($f['nonce']);
            $ct = $this->decode($f['ct']);
            if (strlen($nonce) !== 24 || strlen($ct) < 17 || strlen($ct) > 4112) {
                $this->fail();
            }
            $plain = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt($ct, $this->aad($context), $nonce, $key);
            if ($plain === false) {
                $this->fail();
            }
            $payload = json_decode($plain, true, 8, JSON_THROW_ON_ERROR);
            if (! is_array($payload) || $this->payload($payload, $context) !== $plain) {
                $this->fail();
            }

            return $payload;
        } catch (Throwable) {
            $this->fail();
        }
    }

    public function matchesContents(array $context, #[\SensitiveParameter] array $payload, #[\SensitiveParameter] string $identity, #[\SensitiveParameter] string $phone): bool
    {
        try {
            [, $binding] = $this->keys($context, 'decrypt');
            $this->payload($payload, $context);

            return hash_equals($payload['identity_content_mac'], $this->mac('identity', $identity, $context, $binding)) && hash_equals($payload['contact_content_mac'], $this->mac('contact', $phone, $context, $binding));
        } catch (Throwable) {
            $this->fail();
        }
    }

    private function encode(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    private function decode(string $text): string
    {
        if (! preg_match('/^[A-Za-z0-9_-]+$/D', $text)) {
            $this->fail();
        }
        $bytes = base64_decode(strtr($text, '-_', '+/'), true);
        if ($bytes === false || $this->encode($bytes) !== $text) {
            $this->fail();
        }

        return $bytes;
    }
}
