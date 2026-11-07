<?php

namespace Tests\Unit;

use LogicException;
use PHPUnit\Framework\TestCase;
use Tests\Support\SyntheticCipher;
use Tests\Support\SyntheticKeys;

class B32SyntheticCipherTest extends TestCase
{
    private function binding(): array
    {
        return ['namespace' => 'synthetic', 'merchant' => '01J00000000000000000000000', 'record' => '01J00000000000000000000001', 'purpose' => 'identity_declaration', 'version' => 1, 'actor' => 1];
    }

    private function unavailable(callable $operation): void
    {
        try {
            $operation();
        } catch (LogicException $error) {
            $this->assertSame('Sensitive value unavailable.', $error->getMessage());

            return;
        }
        $this->fail('Invalid envelope accepted.');
    }

    public function test_valid_ulid_case_is_canonicalized_only_inside_aad(): void
    {
        $cipher = new SyntheticCipher(new SyntheticKeys);
        $upper = array_replace($this->binding(), ['merchant' => substr($this->binding()['merchant'], 0, 23).'ABC', 'record' => substr($this->binding()['record'], 0, 23).'DEF']);
        $lower = array_replace($upper, ['merchant' => strtolower($upper['merchant']), 'record' => strtolower($upper['record'])]);
        $mixed = array_replace($upper, ['merchant' => str_replace('ABC', 'AbC', $upper['merchant']), 'record' => str_replace('DEF', 'dEf', $upper['record'])]);
        foreach ([$upper, $lower, $mixed] as $source) {
            $original = $source;
            $envelope = $cipher->encrypt('nin', 'SYNTHETIC-CASE', $source, 'nin.v1');
            foreach ([$upper, $lower, $mixed] as $expected) {
                $this->assertSame('SYNTHETIC-CASE', $cipher->decrypt('nin', $envelope, $expected, 'nin.v1')->forCryptoAssertion());
            }
            $this->assertSame($original, $source);
            $different = array_replace($mixed, ['merchant' => substr($upper['merchant'], 0, 23).'ABD']);
            $this->unavailable(fn () => $cipher->decrypt('nin', $envelope, $different, 'nin.v1'));
        }
    }

    public function test_invalid_ulids_are_rejected_before_aad_canonicalization(): void
    {
        $cipher = new SyntheticCipher(new SyntheticKeys);
        $envelope = $cipher->encrypt('nin', 'SYNTHETIC-CASE', $this->binding(), 'nin.v1');
        $invalid = ['', str_repeat('0', 25), str_repeat('0', 27), '81J00000000000000000000000', '01I00000000000000000000000', '01l00000000000000000000000', '01o00000000000000000000000', '01u00000000000000000000000', "01J00000000000000000000000\n", ' 1J00000000000000000000000', '01J0000000000000000000000é'];
        foreach (['merchant', 'record'] as $field) {
            foreach ($invalid as $value) {
                $binding = array_replace($this->binding(), [$field => $value]);
                $this->unavailable(fn () => $cipher->encrypt('nin', 'SYNTHETIC-CASE', $binding, 'nin.v1'));
                $this->unavailable(fn () => $cipher->decrypt('nin', $envelope, $binding, 'nin.v1'));
            }
        }
    }

    public function test_round_trip_nonce_rotation_and_no_serialization(): void
    {
        $keys = new SyntheticKeys;
        $cipher = new SyntheticCipher($keys);
        $first = $cipher->encrypt('nin', '000SYNTHETIC-NIN', $this->binding(), 'nin.v1');
        $this->assertStringNotContainsString('000SYNTHETIC-NIN', $first);
        $second = $cipher->encrypt('nin', '000SYNTHETIC-NIN', $this->binding(), 'nin.v1');
        $this->assertNotSame(json_decode($first)->nonce, json_decode($second)->nonce);
        $value = $cipher->decrypt('nin', $first, $this->binding(), 'nin.v1');
        $this->assertSame('000SYNTHETIC-NIN', $value->forCryptoAssertion());
        $this->assertStringNotContainsString('000SYNTHETIC-NIN', print_r($value, true));
        try {
            json_encode($value, JSON_THROW_ON_ERROR);
            $this->fail('Serialization exposed a sensitive value.');
        } catch (LogicException) {
            $this->addToAssertionCount(1);
        }
        $rotated = $cipher->rotate('nin', $first, $this->binding(), 'nin.v1', 1, 'nin.v2');
        $keys->forget('nin', 'nin.v1');
        $this->assertSame('000SYNTHETIC-NIN', $cipher->decrypt('nin', $rotated, $this->binding(), 'nin.v2', 2)->forCryptoAssertion());
        $this->unavailable(fn () => $cipher->decrypt('nin', $first, $this->binding(), 'nin.v1'));
    }

    public function test_wrong_keys_contexts_and_malformed_envelopes_are_rejected(): void
    {
        $cipher = new SyntheticCipher(new SyntheticKeys);
        $binding = $this->binding();
        $envelope = $cipher->encrypt('nin', 'SYNTHETIC-NIN', $binding, 'nin.v1');
        foreach (['merchant' => '01J00000000000000000000002', 'record' => '01J00000000000000000000003', 'actor' => 2, 'version' => 2, 'purpose' => 'application_phone', 'namespace' => 'other'] as $field => $value) {
            $this->unavailable(fn () => $cipher->decrypt('nin', $envelope, array_replace($binding, [$field => $value]), 'nin.v1'));
        }
        $this->unavailable(fn () => $cipher->decrypt('nin', $envelope, $binding, 'nin.v2'));
        $this->unavailable(fn () => $cipher->decrypt('nin', $envelope, $binding, 'nin.v1', 2));
        foreach (['ct', 'nonce', 'alg', 'kid', 'rev', 'v'] as $field) {
            $fields = json_decode($envelope, true);
            $fields[$field] = match ($field) {
                'rev', 'v' => 2, 'ct', 'nonce' => str_repeat('A', strlen($fields[$field])), default => 'invalid'
            };
            $bad = json_encode($fields, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            $this->unavailable(fn () => $cipher->decrypt('nin', $bad, $binding, 'nin.v1'));
        }
        foreach (['{}', '{bad', str_repeat('A', 4097), str_replace('"v":1', '"v":1,"v":1', $envelope), str_replace('"v":1', '"v":"1"', $envelope)] as $bad) {
            $this->unavailable(fn () => $cipher->decrypt('nin', $bad, $binding, 'nin.v1'));
        }
    }
}
