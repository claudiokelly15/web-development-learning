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
        Schema::create('merchant_memberships', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('merchant_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->enum('role', ['owner', 'manager', 'staff']);
            $table->enum('status', ['active', 'revoked'])->default('active');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->unique(['merchant_id', 'user_id']);
            $table->index(['user_id', 'status']);
            $table->index(['merchant_id', 'role', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_memberships');
    }
};
