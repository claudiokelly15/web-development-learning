<?php

use App\Actions\Payments\NotificationSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $connection = DB::connection($this->getConnection());
        if ($connection->getDriverName() !== 'sqlite') {
            throw new LogicException('P10 requires reviewed SQLite provisioning.');
        }
        foreach (NotificationSchema::statements() as $sql) {
            $connection->unprepared($sql);
        }
    }

    public function down(): void
    {
        throw new LogicException('P10 audit history must be retained; use reviewed backup/recovery, not destructive rollback.');
    }
};
