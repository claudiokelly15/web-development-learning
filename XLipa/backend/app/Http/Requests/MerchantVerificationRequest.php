<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Gate;

class MerchantVerificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manageVerification', $this->route('merchant'));
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('phone'))) {
            $phone = preg_replace('/[ ()-]/', '', $this->input('phone'));
            if (preg_match('/^0[67][0-9]{8}$/D', $phone)) {
                $phone = '+255'.substr($phone, 1);
            }
            $this->merge(['phone' => $phone]);
        }
    }

    public function rules(): array
    {
        $action = $this->route()->getActionMethod();
        $base = ['context_version' => ['required', 'integer', 'min:0', 'max:2147483647'], 'password' => ['required', 'string', 'max:200', 'current_password:web']];

        return match ($action) {
            'contact' => [...$base, 'phone' => ['required', 'string', 'regex:/^\+255[67][0-9]{8}$/D']],
            'identity' => [...$base, 'nin' => ['required', 'string', 'max:256', 'not_regex:/[\x00-\x1f\x7f]/'], 'subject_claim' => ['required', 'in:individual_owner,authorized_representative'], 'declaration' => ['accepted']],
            'requestCode' => ['request_id' => ['required', 'uuid', 'regex:/^[0-9a-f-]{36}$/D']],
            'verify' => ['challenge' => ['required', 'ulid'], 'code' => ['required', 'string', 'max:128']],
            default => [],
        };
    }

    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $validator): void {
            if (array_diff(array_keys($this->all()), [...array_keys($this->rules()), '_token'])) {
                $validator->errors()->add('verification', 'Unsupported verification input.');
            }
        });
    }

    protected function failedValidation(Validator $validator): never
    {
        $message = 'Check the required fields and your password. Sensitive values have not been retained.';
        if ($this->expectsJson()) {
            throw new HttpResponseException(response()->json(['message' => $message], 422));
        }
        throw new HttpResponseException(redirect()->route('merchants.verification', ['merchant' => $this->route('merchant')->id])->withErrors(['verification' => $message]));
    }
}
