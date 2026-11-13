<?php

use App\Actions\Merchants\BusinessNameCompletionSchema;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        BusinessNameCompletionSchema::createObjects();
    }

    public function down(): void
    {
        throw new LogicException('BNC1 downgrade disabled. Reviewed forward recovery required.');
    }
};
