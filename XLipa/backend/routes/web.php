<?php

use App\Http\Controllers\Auth\AccountController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Middleware\AuthenticationResponseHeaders;
use App\Http\Middleware\EnsureActiveAuthentication;
use App\Http\Middleware\EnsureAuthenticationReady;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::view('/', 'xlipa.xlipahomepage')->name('home');

foreach ([
    'xlipahomepage',
    'about',
    'contact',
    'developers',
    'disclaimer',
    'privacy',
    'prohibited-businesses',
    'terms',
] as $page) {
    Route::view('/'.$page.'.html', 'xlipa.'.$page)->name('pages.'.$page);
}

Route::middleware([AuthenticationResponseHeaders::class, 'guest'])->group(function (): void {
    Route::view('/xlipasignin.html', 'xlipa.xlipasignin')->name('login');
    Route::view('/xlipacreateaccount.html', 'xlipa.xlipacreateaccount')->name('register');
    Route::view('/forgot-password', 'auth.forgot-password')->name('password.request');
    Route::get('/reset-password/{token}', fn (Request $request, string $token) => view('auth.reset-password', ['token' => $token, 'email' => is_string($request->query('email')) ? $request->query('email') : '']))->name('password.reset');
    Route::middleware(EnsureAuthenticationReady::class)->group(function (): void {
        Route::post('/register', [RegisteredUserController::class, 'store'])->middleware('throttle:auth-register')->name('register.store');
        Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:auth-login')->name('login.store');
        Route::post('/forgot-password', [PasswordResetController::class, 'requestLink'])->middleware('throttle:auth-reset-link')->name('password.email');
        Route::post('/reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:auth-reset')->name('password.update');
    });
});

Route::middleware([AuthenticationResponseHeaders::class, 'auth'])->group(function (): void {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::middleware([EnsureAuthenticationReady::class, EnsureActiveAuthentication::class])->group(function (): void {
        Route::get('/email/verify', [EmailVerificationController::class, 'notice'])->name('verification.notice');
        Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])->middleware(['signed', 'throttle:auth-verify'])->name('verification.verify');
        Route::post('/email/verification-notification', [EmailVerificationController::class, 'resend'])->middleware('throttle:auth-verify')->name('verification.send');
        Route::get('/account', [AccountController::class, 'show'])->middleware('verified')->name('account');
    });
});
