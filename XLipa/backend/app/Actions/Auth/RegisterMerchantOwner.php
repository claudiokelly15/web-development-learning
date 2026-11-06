<?php

namespace App\Actions\Auth;

use App\Actions\Merchants\CreateMerchant;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RegisterMerchantOwner
{
    /** @param array{fullName:string,businessName:string,email:string,phone:string,password:string,terms:mixed} $data */
    public function execute(#[\SensitiveParameter] array $data): void
    {
        $hash = Hash::make($data['password']);
        if (User::query()->where('email', $data['email'])->exists()) {
            return;
        }
        try {
            $user = DB::transaction(function () use ($data, $hash): User {
                $user = User::create(['name' => $data['fullName'], 'email' => $data['email'], 'password' => $hash]);
                $user->forceFill(['terms_version' => '2026-11-06', 'terms_accepted_at' => now()])->save();
                $merchant = app(CreateMerchant::class)->execute($user, $data['businessName']);
                $merchant->forceFill(['business_phone' => $data['phone']])->save();

                return $user;
            }, 3);
        } catch (UniqueConstraintViolationException $error) {
            if (User::query()->where('email', $data['email'])->exists()) {
                return;
            }
            throw $error;
        }
        event(new Registered($user));
    }
}
