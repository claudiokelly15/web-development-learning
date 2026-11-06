<?php

namespace App\Http\Requests\Auth;

class ResetPasswordRequest extends RegisterRequest
{
    protected function getRedirectUrl(): string
    {
        $token = $this->input('token');
        $email = $this->input('email');

        return is_string($token) && strlen($token) <= 256 && is_string($email) && strlen($email) <= 254
            ? route('password.reset', ['token' => $token, 'email' => $email])
            : route('password.request');
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => strtolower(trim($this->input('email')))]);
        }
    }

    public function rules(): array
    {
        $registrationRules = parent::rules();

        return ['email' => $registrationRules['email'], 'password' => $registrationRules['password'], 'token' => ['required', 'string', 'max:256']];
    }
}
