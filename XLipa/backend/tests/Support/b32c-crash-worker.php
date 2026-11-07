<?php

use Illuminate\Contracts\Console\Kernel;
use Tests\Support\B32CRehearsal;

require dirname(__DIR__).'/bootstrap-isolated.php';

$path = $argv[1] ?? '';
if (realpath(dirname($path)) !== realpath(sys_get_temp_dir()) || ! preg_match('/^xlipa-b32-synthetic-[a-f0-9]+\.sqlite$/D', basename($path)) || ! is_file($path)) {
    throw new LogicException('B32C crash worker requires an existing isolated fixture.');
}
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->useEnvironmentPath(dirname(__DIR__))->loadEnvironmentFrom('__no_real_environment');
$app->make(Kernel::class)->bootstrap();
config(['database.connections.sqlite.database' => $path, 'b32c.selected' => realpath(B32CRehearsal::artifact()), 'b32c.rehearsal_hook' => static function (string $phase) use ($path): void {
    if ($phase === 'after_commit_before_ledger') {
        file_put_contents($path.'.committed', 'fixture barrier');
        $deadline = microtime(true) + 15;
        while (microtime(true) < $deadline) {
            usleep(10000);
        }
        throw new LogicException('Crash worker was not terminated.');
    }
}]);
B32CRehearsal::activate();
