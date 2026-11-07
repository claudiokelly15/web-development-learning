<?php

require dirname(__DIR__).'/bootstrap-isolated.php';

use Tests\Support\OfflinePermit;
use Tests\Support\OfflineRunRegister;

$root = $argv[1] ?? '';
$worker = $argv[2] ?? '';
$register = new OfflineRunRegister($root);
if (! in_array($worker, ['0', '1'], true)) {
    throw new LogicException('Invalid fixture worker.');
}
$input = json_decode(file_get_contents($root.'/public-input.json'), true, 16, JSON_THROW_ON_ERROR);
$permit = OfflinePermit::verify($input['bytes'], $input['signature'], hex2bin($input['public']), $input['expected'], [], $input['clock']);
file_put_contents($root.'/ready'.$worker, 'fixture');
$deadline = microtime(true) + 15;
while (! is_file($root.'/go')) {
    if (microtime(true) > $deadline) {
        throw new LogicException('Offline fixture barrier timeout.');
    }usleep(10000);
}
try {
    $register->claim($permit);
    echo json_encode(['claimed' => true]);
} catch (LogicException $error) {
    if ($error->getMessage() !== 'Offline permit already consumed.') {
        throw $error;
    }echo json_encode(['claimed' => false]);
}
