<?php

namespace Tests\Support;

use LogicException;
use Throwable;

final class SyntheticCipher
{
    private const ALGORITHM = 'xchacha20poly1305-ietf';

    public function __construct(private readonly SyntheticKeys $keys) {}

    public static function lp(string ...$parts): string
    {
        return implode('', array_map(fn (string $part): string => pack('N', strlen($part)).$part, $parts));
    }

    private function aad(string $domain, string $kid, int $revision, array $binding): string
    {
        if (! in_array($domain, ['nin', 'phone'], true) || ! preg_match('/^[a-z0-9._-]{1,64}$/D', $kid) || $revision < 1
            || array_keys($binding) !== ['namespace', 'merchant', 'record', 'purpose', 'version', 'actor']
            || ! is_int($binding['version']) || $binding['version'] < 1 || ! is_int($binding['actor']) || $binding['actor'] < 1
            || ! is_string($binding['namespace']) || ! preg_match('/^[a-z0-9._-]{1,64}$/D', $binding['namespace'])) {
            throw new LogicException('Sensitive value unavailable.');
        }
        foreach (['merchant', 'record'] as $field) {
            if (! is_string($binding[$field]) || ! preg_match('/^[0-7][0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{25}$/D', $binding[$field])) {
                throw new LogicException('Sensitive value unavailable.');
            }
            $binding[$field] = strtoupper($binding[$field]);
        }
        if ($binding['purpose'] !== ($domain === 'nin' ? 'identity_declaration' : 'application_phone')) {
            throw new LogicException('Sensitive value unavailable.');
        }

        return self::lp('xlipa-sensitive-v1', $binding['namespace'], self::ALGORITHM, '1', $kid, (string) $revision, $domain, $binding['merchant'], $binding['record'], $binding['purpose'], (string) $binding['version'], (string) $binding['actor']);
    }

    private function encode(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    private function decode(string $encoded): string
    {
        if (! preg_match('/^[A-Za-z0-9_-]+$/D', $encoded)) {
            throw new LogicException('Sensitive value unavailable.');
        }
        $bytes = base64_decode(strtr($encoded, '-_', '+/'), true);
        if ($bytes === false || $this->encode($bytes) !== $encoded) {
            throw new LogicException('Sensitive value unavailable.');
        }

        return $bytes;
    }

    public function encrypt(string $domain, string $plaintext, array $binding, string $kid, int $revision = 1): string
    {
        try {
            if (strlen($plaintext) < 1 || strlen($plaintext) > ($domain === 'phone' ? 16 : 256)) {
                throw new LogicException;
            }
            $aad = $this->aad($domain, $kid, $revision, $binding);
            $key = $this->keys->resolve($domain, $kid);
            $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
            $ct = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($plaintext, $aad, $nonce, $key);

            return json_encode(['v' => 1, 'alg' => self::ALGORITHM, 'kid' => $kid, 'rev' => $revision, 'nonce' => $this->encode($nonce), 'ct' => $this->encode($ct)], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (Throwable) {
            throw new LogicException('Sensitive value unavailable.');
        }
    }

    public function decrypt(string $domain, string $envelope, array $binding, string $kid, int $revision = 1): SensitiveFixtureValue
    {
        try {
            if (strlen($envelope) > 4096) {
                throw new LogicException;
            }
            $fields = json_decode($envelope, true, 8, JSON_THROW_ON_ERROR);
            if (! is_array($fields) || array_keys($fields) !== ['v', 'alg', 'kid', 'rev', 'nonce', 'ct']
                || $fields['v'] !== 1 || $fields['alg'] !== self::ALGORITHM || $fields['kid'] !== $kid || $fields['rev'] !== $revision
                || ! is_string($fields['nonce']) || ! is_string($fields['ct'])
                || json_encode($fields, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) !== $envelope) {
                throw new LogicException;
            }
            $nonce = $this->decode($fields['nonce']);
            $ct = $this->decode($fields['ct']);
            if (strlen($nonce) !== 24 || strlen($ct) < 17 || strlen($ct) > 272) {
                throw new LogicException;
            }
            $plaintext = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt($ct, $this->aad($domain, $kid, $revision, $binding), $nonce, $this->keys->resolve($domain, $kid));
            if ($plaintext === false || strlen($plaintext) > ($domain === 'phone' ? 16 : 256)) {
                throw new LogicException;
            }

            return new SensitiveFixtureValue($plaintext);
        } catch (Throwable) {
            throw new LogicException('Sensitive value unavailable.');
        }
    }

    public function rotate(string $domain, string $envelope, array $binding, string $oldKid, int $revision, string $newKid): string
    {
        return $this->encrypt($domain, $this->decrypt($domain, $envelope, $binding, $oldKid, $revision)->forCryptoAssertion(), $binding, $newKid, $revision + 1);
    }
}
