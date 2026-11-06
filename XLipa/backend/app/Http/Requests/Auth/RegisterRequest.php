<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    protected $redirectRoute = 'register';

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => strtolower(trim($this->input('email')))]);
        }
        if (is_string($this->input('phone'))) {
            $phone = preg_replace('/[\s()-]+/', '', $this->input('phone'));
            if (preg_match('/^0[67][0-9]{8}$/', $phone)) {
                $phone = '+255'.substr($phone, 1);
            }
            $this->merge(['phone' => $phone]);
        }
    }

    public function rules(): array
    {
        return [
            'fullName' => ['required', 'string', 'max:200'], 'businessName' => ['required', 'string', 'max:200'],
            'email' => ['required', 'string', 'email:rfc', 'max:254', 'regex:/^[\x00-\x7F]+$/'],
            'phone' => ['required', 'string', 'regex:/^\+[1-9][0-9]{7,14}$/'],
            'password' => ['required', 'string', 'confirmed', Password::min(12), function (string $attribute, mixed $value, \Closure $fail): void {
                if (is_string($value) && (strlen($value) > 72 || str_contains($value, "\0"))) {
                    $fail('Use a password without null bytes and no longer than 72 bytes.');
                }
            }],
            'terms' => ['accepted'], 'role' => ['prohibited'], 'platform_role' => ['prohibited'], 'account_status' => ['prohibited'], 'auth_version' => ['prohibited'], 'merchant_id' => ['prohibited'], 'verification_status' => ['prohibited'], 'operational_status' => ['prohibited'], 'user_id' => ['prohibited'], 'is_admin' => ['prohibited'],
        ];
    }
}
