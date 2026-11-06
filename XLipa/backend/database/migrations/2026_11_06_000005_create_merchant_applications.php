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
        Schema::create('merchant_applications', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('merchant_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('attempt_number');
            $table->enum('current_slot', ['1'])->nullable();
            $table->enum('status', ['draft', 'pending_review', 'information_requested', 'approved', 'rejected'])->default('draft');
            $table->unsignedInteger('profile_version')->default(1);
            $table->text('profile_snapshot')->nullable();
            $table->foreignId('submitted_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('public_feedback')->nullable();
            $table->text('internal_review_notes')->nullable();
            $table->timestamps();
            $table->unique(['merchant_id', 'attempt_number']);
            $table->unique(['merchant_id', 'current_slot']);
            $table->unique(['id', 'merchant_id']);
            $table->index(['status', 'submitted_at']);
            $table->index(['merchant_id', 'created_at']);
        });
        foreach (['INSERT', 'UPDATE'] as $operation) {
            DB::unprepared("CREATE TRIGGER applications_versions_{$operation} BEFORE {$operation} ON merchant_applications WHEN typeof(NEW.attempt_number) != 'integer' OR NEW.attempt_number < 1 OR typeof(NEW.profile_version) != 'integer' OR NEW.profile_version < 1 BEGIN SELECT RAISE(ABORT, 'Application versions must be positive integers'); END");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_applications');
    }
};
