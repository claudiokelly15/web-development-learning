<?php

require dirname(__DIR__).'/bootstrap-isolated.php';

use Tests\Support\SyntheticAuditReconciler;
use Tests\Support\SyntheticKeys;
use Tests\Support\SyntheticVerification;

$payload = json_decode($argv[1], true, 16, JSON_THROW_ON_ERROR);
$pdo = new PDO('sqlite:'.$payload['path']);
$pause = static function () use ($payload): void {
    file_put_contents($payload['path'].'.phase', 'reached');
    $until = microtime(true) + 15;
    while (microtime(true) < $until) {
        usleep(10000);
    }
    throw new LogicException('Synthetic crash test was not terminated.');
};
$service = new SyntheticVerification($pdo, new SyntheticKeys, ($payload['operation'] === 'crash_after_count') ? $pause : null, true, ($payload['operation'] === 'crash_before_count') ? $pause : null);
file_put_contents($payload['path'].'.ready'.$payload['worker'], 'ready');
$deadline = microtime(true) + 15;
while (! file_exists($payload['path'].'.go')) {
    if (microtime(true) > $deadline) {
        throw new LogicException('Synthetic barrier timeout.');
    }
    usleep(10000);
}
$result = match ($payload['operation']) {
    'guess' => $service->verify($payload['binding'], $payload['challenge'], $payload['code'], $payload['now']),
    'consume' => $service->consume($payload['binding'], $payload['proof'], $payload['application'], $payload['now']),
    'resend' => $service->issue($payload['binding'], $payload['now'], $payload['request']),
    'reconcile' => (new SyntheticAuditReconciler($pdo))->reconcile($payload['scope'], $payload['event']),
    'crash_before_count', 'crash_after_count' => $service->verify($payload['binding'], $payload['challenge'], '999999', $payload['now']),
    'crash_after_insert' => (new SyntheticAuditReconciler($pdo, $pause))->reconcile($payload['scope'], $payload['event']),
    'crash_after_reconcile' => (new SyntheticAuditReconciler($pdo))->reconcile($payload['scope'], $payload['event']),
};
if ($payload['operation'] === 'crash_after_reconcile') {
    $pause();
}
echo json_encode(['result' => $result], JSON_THROW_ON_ERROR);
