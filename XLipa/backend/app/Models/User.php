<?php

namespace App\Models;

use App\Actions\Merchants\LifecycleMutation;
use App\Enums\AccountStatus;
use App\Notifications\VersionedVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    public function sendEmailVerificationNotification(): void
    {
        if (app(LifecycleMutation::class)->versioned()) {
            $this->fresh()->notify(new VersionedVerifyEmail);
        } else {
            $this->notify(new VerifyEmail);
        }
    }

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function email(): Attribute
    {
        return Attribute::make(set: fn (string $value): string => strtolower(trim($value)));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'account_status' => AccountStatus::class,
            'auth_version' => 'integer',
            'terms_accepted_at' => 'immutable_datetime',
        ];
    }

    public function merchantMemberships(): HasMany
    {
        return $this->hasMany(MerchantMembership::class);
    }

    public function merchants(): BelongsToMany
    {
        return $this->belongsToMany(Merchant::class, 'merchant_memberships')->withPivot(['role', 'status', 'revoked_at'])->withTimestamps();
    }

    public function platformRoleAssignment(): HasOne
    {
        return $this->hasOne(PlatformRoleAssignment::class);
    }
}
