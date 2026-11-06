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
        Schema::create('merchants', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('business_name', 200);
            $table->string('business_type', 50)->nullable();
            $table->char('registration_country', 2)->nullable();
            $table->string('registration_number', 100)->nullable();
            $table->string('business_email', 254)->nullable();
            $table->string('business_phone', 16)->nullable();
            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('region', 100)->nullable();
            $table->char('country_code', 2)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->enum('operational_status', ['onboarding', 'enabled', 'suspended', 'closed'])->default('onboarding');
            $table->enum('verification_status', ['unverified', 'pending', 'needs_information', 'verified', 'rejected'])->default('unverified');
            $table->unsignedInteger('profile_version')->default(1);
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['operational_status', 'created_at']);
            $table->index(['verification_status', 'updated_at']);
            $table->index(['registration_country', 'registration_number']);
        });
        foreach (['INSERT', 'UPDATE'] as $operation) {
            DB::unprepared("CREATE TRIGGER merchants_profile_version_{$operation} BEFORE {$operation} ON merchants WHEN typeof(NEW.profile_version) != 'integer' OR NEW.profile_version < 1 BEGIN SELECT RAISE(ABORT, 'profile_version must be a positive integer'); END");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('merchants');
    }
};
