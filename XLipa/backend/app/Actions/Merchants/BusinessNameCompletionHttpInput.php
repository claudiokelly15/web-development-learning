<?php

namespace App\Actions\Merchants;

use InvalidArgumentException;

/** Pure raw-body validation only. Does not validate CSRF, authenticate or authorize dispatch. */
final class BusinessNameCompletionHttpInput
{
    /** @return array{_token:string,context_ref:string,business_name?:string} Private request data. */
    public function parse(string $contentType, #[\SensitiveParameter] string $body, #[\SensitiveParameter] string $query = '', bool $resolving = false): array
    {
        $this->require($query === '' && strlen($body) <= 4096);
        $this->require((bool) preg_match('/\Aapplication\/x-www-form-urlencoded(?:[ \t]*;[ \t]*charset=(?:UTF-8|"UTF-8"))?[ \t]*\z/iD', $contentType));
        $allowed = $resolving ? ['_token', 'context_ref'] : ['_token', 'context_ref', 'business_name'];
        $fields = [];
        foreach (explode('&', $body) as $pair) {
            $this->require(substr_count($pair, '=') === 1);
            [$key, $raw] = explode('=', $pair, 2);
            $this->require(in_array($key, $allowed, true) && ! array_key_exists($key, $fields));
            $this->require(! preg_match('/%(?![0-9a-f]{2})/i', $raw));
            if ($key === 'business_name') {
                $this->require(strlen($raw) <= 3072);
            }
            $value = rawurldecode(str_replace('+', ' ', $raw));
            $this->require(mb_check_encoding($value, 'UTF-8'));
            $fields[$key] = $value;
        }
        $this->require(count($fields) === count($allowed));
        $this->require((bool) preg_match('/\A[A-Za-z0-9_-]{1,128}\z/D', $fields['context_ref']));
        $this->require((bool) preg_match('/\A[\x21-\x7e]{1,256}\z/D', $fields['_token']));
        if (! $resolving) {
            $this->require(strlen($fields['business_name']) <= 1024);
            $fields['business_name'] = $this->name($fields['business_name']);
        }

        return $fields;
    }

    /** Same byte/code-point contract as the frozen internal validator; no transport cap here. */
    public function name(#[\SensitiveParameter] mixed $name): string
    {
        $this->require(is_string($name) && mb_check_encoding($name, 'UTF-8') && ! preg_match('/[\p{Cc}\p{Cf}\p{Zl}\p{Zp}]/u', $name));
        $name = trim($name, ' ');
        $this->require($name !== '' && mb_strlen($name, 'UTF-8') <= 200 && ! preg_match('/^\p{Z}|\p{Z}$/u', $name));

        return $name;
    }

    /** Validate server-custodied types only. Browser expectations and decimal coercion are forbidden. */
    public function expectations(string $merchant, #[\SensitiveParameter] array $expected): void
    {
        $this->require(count($expected) === count(BusinessNameCompletionContext::EXPECTATIONS) && ! array_diff(array_keys($expected), BusinessNameCompletionContext::EXPECTATIONS));
        foreach (['merchant_id' => $merchant, ...$expected] as $field => $value) {
            $valid = match ($field) {
                'merchant_id', 'application_id' => is_string($value) && preg_match('/^[0-7][0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{25}$/D', $value),
                'completion_request_id' => is_string($value) && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/D', $value),
                default => is_int($value) && $value > 0,
            };
            $this->require((bool) $valid);
        }
    }

    private function require(bool $condition): void
    {
        if (! $condition) {
            throw new InvalidArgumentException('Business name completion input could not be accepted.');
        }
    }
}
