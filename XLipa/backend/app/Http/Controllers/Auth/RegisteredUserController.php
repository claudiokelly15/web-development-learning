<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\RegisterMerchantOwner;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use Illuminate\Http\RedirectResponse;

class RegisteredUserController extends Controller
{
    public function store(RegisterRequest $request, RegisterMerchantOwner $register): RedirectResponse
    {
        $register->execute($request->validated());

        return redirect()->route('login')->with('status', 'If your details can be used, check your email to verify your account, then sign in.');
    }
}
