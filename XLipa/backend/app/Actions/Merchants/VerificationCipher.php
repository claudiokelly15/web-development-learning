<?php

namespace App\Actions\Merchants;

use LogicException;
use Throwable;

/** B3.2 envelope-v1 interoperability; bindings come from authoritative rows, never envelopes. */
final class VerificationCipher
{
    public function __construct(private readonly VerificationEnvironment $environment) {}

    public static function lp(string ...$parts): string
    {
        return implode('', array_map(fn (string $part): string => pack('N', strlen($part)).$part, $parts));
    }

    private function aad(string $domain, string $kid, int $revision, array $binding): string
    {
        if (! in_array($domain, ['nin', 'phone'], true) || ! preg_match('/^[a-z0-9._-]{1,64}$/D', $kid) || $revision < 1
            || array_keys($binding) !== ['namespace', 'merchant', 'record', 'purpose', 'version', 'actor']
            || ! is_int($binding['version']) || $binding['version'] < 1 || ! is_int($binding['actor']) || $binding['actor'] < 1
            || ! preg_match('/^[a-z0-9._-]{1,64}$/D', $binding['namespace'])) {
            throw new LogicException;
        }
        foreach (['merchant', 'record'] as $field) {
            if (! preg_match('/^[0-7][0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{25}$/D', $binding[$field])) {
                throw new LogicException;
            }
            $binding[$field] = strtoupper($binding[$field]);
        }
        if ($binding['purpose'] !== ($domain === 'nin' ? 'identity_declaration' : 'application_phone')) {
            throw new LogicException;
        }

        return self::lp('xlipa-sensitive-v1', $binding['namespace'], 'xchacha20poly1305-ietf', '1', $kid, (string) $revision, $domain, $binding['merchant'], $binding['record'], $binding['purpose'], (string) $binding['version'], (string) $binding['actor']);
    }

    private function key(string $domain, string $kid): string
    {
        $key = $this->environment->key($domain, $kid);
        if (strlen($key) !== 32) {
            throw new LogicException;
        }

        return $key;
    }

    private function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function decode(string $value): string
    {
        if (! preg_match('/^[A-Za-z0-9_-]+$/D', $value)) {
            throw new LogicException;
        }
        $bytes = base64_decode(strtr($value, '-_', '+/'), true);
        if ($bytes === false || $this->encode($bytes) !== $value) {
            throw new LogicException;
        }

        return $bytes;
    }

    public function encrypt(string $domain, #[\SensitiveParameter] string $plaintext, array $binding, string $kid, int $revision = 1): string
    {
        try {
            if (strlen($plaintext) < 1 || strlen($plaintext) > ($domain === 'phone' ? 16 : 256)) {
                throw new LogicException;
            }
            $aad = $this->aad($domain, $kid, $revision, $binding);
            $nonce = random_bytes(24);
            $ct = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($plaintext, $aad, $nonce, $this->key($domain, $kid));

            return json_encode(['v' => 1, 'alg' => 'xchacha20poly1305-ietf', 'kid' => $kid, 'rev' => $revision, 'nonce' => $this->encode($nonce), 'ct' => $this->encode($ct)], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (Throwable) {
            throw new LogicException('Sensitive value unavailable.');
        }
    }

    /** Internal use for exact-target verification only; no reveal endpoint or model accessor. */
    public function decrypt(string $domain, #[\SensitiveParameter] string $envelope, array $binding, string $kid, int $revision = 1): string
    {
        try {
            if (strlen($envelope) > 4096) {
                throw new LogicException;
            }
            $f = json_decode($envelope, true, 8, JSON_THROW_ON_ERROR);
            if (! is_array($f) || array_keys($f) !== ['v', 'alg', 'kid', 'rev', 'nonce', 'ct'] || $f['v'] !== 1 || $f['alg'] !== 'xchacha20poly1305-ietf' || $f['kid'] !== $kid || $f['rev'] !== $revision || ! is_string($f['nonce']) || ! is_string($f['ct']) || json_encode($f, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) !== $envelope) {
                throw new LogicException;
            }
            $nonce = $this->decode($f['nonce']);
            $ct = $this->decode($f['ct']);
            if (strlen($nonce) !== 24 || strlen($ct) < 17 || strlen($ct) > 272) {
                throw new LogicException;
            }
            $plain = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt($ct, $this->aad($domain, $kid, $revision, $binding), $nonce, $this->key($domain, $kid));
            if ($plain === false || strlen($plain) > ($domain === 'phone' ? 16 : 256)) {
                throw new LogicException;
            }

            return $plain;
        } catch (Throwable) {
            throw new LogicException('Sensitive value unavailable.');
        }
    }
}
