<?php

use App\Actions\Payments\NotificationSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $connection = DB::connection($this->getConnection());
        if ($connection->getDriverName() !== 'sqlite') {
            throw new LogicException('P10 requires reviewed SQLite provisioning.');
        }
        foreach (NotificationSchema::completionStatements() as $sql) {
            $connection->unprepared($sql);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        throw new LogicException('P10 policy and document history must be retained.');
    }
};
