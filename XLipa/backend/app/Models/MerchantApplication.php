<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class MerchantApplication extends Model
{
    use HasFactory, HasUlids;

    protected $guarded = ['*'];

    protected $hidden = ['profile_snapshot', 'internal_review_notes'];

    protected function casts(): array
    {
        return ['status' => ApplicationStatus::class, 'current_slot' => 'integer', 'attempt_number' => 'integer', 'profile_version' => 'integer', 'profile_snapshot' => 'encrypted:array', 'internal_review_notes' => 'encrypted', 'submitted_at' => 'immutable_datetime', 'reviewed_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $application): void {
            if ($application->isDirty(['merchant_id', 'attempt_number'])) {
                throw new LogicException('Application identity cannot be reassigned.');
            }
            if ($application->getRawOriginal('status') !== ApplicationStatus::Draft->value && $application->isDirty(['profile_snapshot', 'profile_version', 'submitted_at', 'submitted_by_user_id'])) {
                throw new LogicException('Submitted application evidence is immutable.');
            }
        });
        static::deleting(function (): void {
            throw new LogicException('Application history cannot be deleted through the model.');
        });
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'application_id');
    }
}
