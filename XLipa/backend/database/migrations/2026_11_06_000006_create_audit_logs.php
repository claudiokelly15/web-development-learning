<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            throw new LogicException('B1 targets SQLite only; another database engine requires migration review.');
        }
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->enum('event_type', ['merchant.created', 'membership.revoked', 'application.submitted', 'application.reviewed', 'platform_role.granted', 'platform_role.revoked', 'account.status_changed', 'merchant.status_changed']);
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('target_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignUlid('merchant_id')->nullable()->constrained()->restrictOnDelete();
            $table->ulid('application_id')->nullable();
            $table->uuid('request_id');
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->foreign(['application_id', 'merchant_id'])->references(['id', 'merchant_id'])->on('merchant_applications')->restrictOnDelete();
            $table->index(['merchant_id', 'occurred_at']);
            $table->index(['actor_user_id', 'occurred_at']);
            $table->index(['event_type', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
