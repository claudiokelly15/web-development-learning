<?php

namespace App\Models;

use App\Enums\MerchantStatus;
use App\Enums\VerificationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Merchant extends Model
{
    use HasFactory, HasUlids;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['operational_status' => MerchantStatus::class, 'verification_status' => VerificationStatus::class, 'profile_version' => 'integer'];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(MerchantMembership::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(MerchantApplication::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'merchant_memberships')->withPivot(['role', 'status', 'revoked_at'])->withTimestamps();
    }

    public function scopeForMember(Builder $query, User $user): Builder
    {
        return $query->whereExists(function ($memberships) use ($user): void {
            $memberships->selectRaw('1')->from('merchant_memberships')
                ->join('users', 'users.id', '=', 'merchant_memberships.user_id')
                ->whereColumn('merchant_memberships.merchant_id', 'merchants.id')
                ->where('users.id', $user->getKey())->where('users.account_status', 'active')
                ->where('merchant_memberships.status', 'active');
        });
    }
}
