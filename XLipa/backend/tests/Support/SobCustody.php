<?php

namespace Tests\Support;

use Closure;
use LogicException;
use Throwable;

/** Sodium AEAD and sealed-box key wrapping; keys/content are disposable fixtures. */
final class SobCustody
{
    public static function seal(string $plaintext, array $provenance, string $public, ?Closure $hook = null): string
    {
        SobFixture::guard();
        if (strlen($public) !== SODIUM_CRYPTO_BOX_PUBLICKEYBYTES || strlen($plaintext) > 262144) {
            throw new LogicException('SOB custody encryption input rejected.');
        }
        $key = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES);
        try {
            $metadata = ['v' => 1, 'algorithm' => 'XChaCha20-Poly1305+sealed-box', 'provenance' => $provenance,
                'nonce' => base64_encode(random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES)), 'wrapped' => base64_encode(sodium_crypto_box_seal($key, $public))];
            SobFixture::phase($hook, 'encryption_started');
            $cipher = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($plaintext, SobFixture::canonical($metadata), base64_decode($metadata['nonce'], true), $key);
            $metadata['ciphertext'] = base64_encode($cipher);

            return SobFixture::canonical($metadata);
        } finally {
            sodium_memzero($key);
        }
    }

    public static function open(string $bytes, ?string $keypair): array
    {
        SobFixture::guard();
        $key = null;
        try {
            if ($keypair === null || strlen($keypair) !== SODIUM_CRYPTO_BOX_KEYPAIRBYTES) {
                throw new LogicException;
            }
            $envelope = SobFixture::decode($bytes);
            SobFixture::keys($envelope, ['v', 'algorithm', 'provenance', 'nonce', 'wrapped', 'ciphertext']);
            if ($envelope['v'] !== 1 || $envelope['algorithm'] !== 'XChaCha20-Poly1305+sealed-box' || ! is_array($envelope['provenance'])) {
                throw new LogicException;
            }
            $binary = [];
            foreach (['nonce', 'wrapped', 'ciphertext'] as $field) {
                $binary[$field] = base64_decode($envelope[$field], true);
                if ($binary[$field] === false || base64_encode($binary[$field]) !== $envelope[$field]) {
                    throw new LogicException;
                }
            }
            if (strlen($binary['nonce']) !== SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES || strlen($binary['wrapped']) !== SODIUM_CRYPTO_BOX_SEALBYTES + SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES) {
                throw new LogicException;
            }
            $key = sodium_crypto_box_seal_open($binary['wrapped'], $keypair);
            if ($key === false) {
                throw new LogicException;
            }
            unset($envelope['ciphertext']);
            $plaintext = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt($binary['ciphertext'], SobFixture::canonical($envelope), $binary['nonce'], $key);
            if ($plaintext === false) {
                throw new LogicException;
            }

            return ['plaintext' => $plaintext, 'provenance' => $envelope['provenance']];
        } catch (Throwable) {
            throw new LogicException('SOB custody authentication/key rejected.');
        } finally {
            if (is_string($key)) {
                sodium_memzero($key);
            }
        }
    }
}
