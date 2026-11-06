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
        Schema::table('users', function (Blueprint $table): void {
            $table->enum('account_status', ['active', 'suspended', 'closed'])->default('active');
            $table->unsignedInteger('auth_version')->default(1);
            $table->string('terms_version', 50)->nullable();
            $table->timestamp('terms_accepted_at')->nullable();
        });
        foreach (['INSERT', 'UPDATE'] as $operation) {
            DB::unprepared("CREATE TRIGGER users_auth_version_{$operation} BEFORE {$operation} ON users WHEN typeof(NEW.auth_version) != 'integer' OR NEW.auth_version < 1 BEGIN SELECT RAISE(ABORT, 'auth_version must be a positive integer'); END");
        }
    }

    public function down(): void
    {
        foreach (['INSERT', 'UPDATE'] as $operation) {
            DB::unprepared("DROP TRIGGER IF EXISTS users_auth_version_{$operation}");
        }
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['account_status', 'auth_version', 'terms_version', 'terms_accepted_at']);
        });
    }
};
