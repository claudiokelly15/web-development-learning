<?php

use App\Actions\Merchants\SubmissionEvidenceSchema;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        SubmissionEvidenceSchema::install();
    }

    public function down(): void
    {
        throw new LogicException('SE1 downgrade disabled; reviewed forward recovery required.');
    }
};
