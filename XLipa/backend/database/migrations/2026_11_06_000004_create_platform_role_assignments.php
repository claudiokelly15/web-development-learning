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
        Schema::create('platform_role_assignments', function (Blueprint $table): void {
            $table->foreignId('user_id')->primary()->constrained('users')->restrictOnDelete();
            $table->enum('role', ['chief_admin', 'admin']);
            $table->foreignId('granted_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('granted_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_role_assignments');
    }
};
