<?php

require dirname(__DIR__).'/bootstrap-isolated.php';

use Tests\Support\SobBoundary;
use Tests\Support\SobFence;
use Tests\Support\SobFixture;
use Tests\Support\SobPermit;
use Tests\Support\SobWitnessRegister;

$input = json_decode(stream_get_contents(STDIN), true, 32, JSON_THROW_ON_ERROR);
$register = new SobWitnessRegister($input['register'], $input['witness']);
$clock = static fn (): array => ['healthy' => true, 'utc' => 1000, 'mono' => 1000];
$status = static fn (): array => ['healthy' => true, 'suspended' => false, 'epoch' => 1, 'at' => 1000, 'revoked' => []];
$request = $input['request'];
$hook = static function (string $phase) use ($input): void {
    if ($phase === ($input['phase'] ?? null)) {
        echo "READY\n";
        flush();
        while (true) {
            usleep(10000);
        }
    }
};
try {
    if ($input['mode'] === 'claim') {
        $permit = SobPermit::verify($request['bytes'], hex2bin($request['signature']), hex2bin($input['public']), $request['expected'], $clock(), $status());
        $register->claim($permit, $hook);
        echo "WON\n";
    } else {
        $fence = new SobFence;
        $generation = $fence->acquire();
        $boundary = new SobBoundary(hex2bin($input['public']), $register, $fence, $clock, $status, $input['release'], $input['reviewed'], hex2bin($input['encryption_public']), static fn (): string => hex2bin($input['encryption_key']));
        $result = $boundary->run($request['bytes'], hex2bin($request['signature']), $request['expected'], $generation, ['artifact.txt'], $hook);
        echo SobFixture::canonical($result)."\n";
    }
} catch (Throwable $error) {
    echo 'DENIED:'.$error->getMessage()."\n";
    exit(2);
}
