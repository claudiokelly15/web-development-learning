<?php

require dirname(__DIR__).'/bootstrap-isolated.php';

use Tests\Support\OfflineStateClassifier;
use Tests\Support\SyntheticRecoveryPackage;

$input = json_decode(stream_get_contents(STDIN), true, 16, JSON_THROW_ON_ERROR);
$base = dirname(__DIR__, 2).'/database/activation/b32c/';
$foundation = json_decode(file_get_contents($base.'selection-manifest.json'), true, 64, JSON_THROW_ON_ERROR);
$contract = json_decode(file_get_contents($base.'security-contract.json'), true, 64, JSON_THROW_ON_ERROR);
$final = $foundation['foundation_objects'];
$final['table:audit_logs'] = hash('sha256', $contract['audit']['sql']);
foreach (['create_tables' => 'table', 'indexes' => 'index', 'triggers' => 'trigger'] as $group => $kind) {
    foreach ($contract[$group] as $name => $sql) {
        $final[$kind.':'.$name] = hash('sha256', $sql);
    }
}
ksort($final);
$classifier = new OfflineStateClassifier($foundation['foundation_objects'], $foundation['foundation_ledger'], $final, '2026_11_07_200000_activate_b32_security_contract');
$tool = new SyntheticRecoveryPackage(hex2bin($input['public']), $input['register'], $classifier, static fn () => ['utc_ms' => 1000, 'mono_ms' => 1000, 'healthy' => true]);
$r = $input['request'];
$tool->run($r['bytes'], $r['signature'], $r['expected'], $r['scope'], $input['release'], $input['manifest'], ['activation/2026_11_07_200000_activate_b32_security_contract.php'], static function (string $phase) use ($input): void {
    if ($phase === $input['phase']) {
        echo "READY\n";
        flush();
        while (true) {
            usleep(10000);
        }
    }
});
