<?php

namespace App\Models;

use App\Enums\AuditEvent;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;
use LogicException;

class AuditLog extends Model
{
    use HasFactory, HasUlids;

    protected $guarded = ['*'];

    public $timestamps = false;

    protected function casts(): array
    {
        return ['event_type' => AuditEvent::class, 'metadata' => 'array', 'occurred_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $event): void {
            $metadata = $event->metadata ?? [];
            $allowed = ['from', 'to', 'role', 'previous_role', 'profile_version', 'reason_code'];
            if (array_diff(array_keys($metadata), $allowed) !== [] || strlen(json_encode($metadata, JSON_THROW_ON_ERROR)) > 2048) {
                throw new InvalidArgumentException('Audit metadata must use the bounded allowlist.');
            }
            foreach ($metadata as $value) {
                if (! is_scalar($value) && $value !== null) {
                    throw new InvalidArgumentException('Nested audit payloads are not allowed.');
                }
            }
            if ($event->application_id !== null && $event->merchant_id === null) {
                throw new InvalidArgumentException('Application events require merchant context.');
            }
        });
        static::updating(function (): void {
            throw new LogicException('Audit records are append-only.');
        });
        static::deleting(function (): void {
            throw new LogicException('Audit records are append-only.');
        });
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(MerchantApplication::class, 'application_id');
    }
}
