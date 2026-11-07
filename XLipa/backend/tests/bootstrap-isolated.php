<?php

// Public fixture values only. This bootstrap never loads a working environment file.
$syntheticEnvironment = [
    'APP_ENV' => 'testing',
    'APP_KEY' => 'base64:'.base64_encode(str_repeat('T', 32)),
    'APP_PREVIOUS_KEYS' => '',
    'APP_URL' => 'http://xlipa.test',
    'APP_CONFIG_CACHE' => __DIR__.'/__absent_synthetic_config.php',
    'APP_ROUTES_CACHE' => __DIR__.'/__absent_synthetic_routes.php',
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => ':memory:',
    'DB_URL' => '',
    'DB_FOREIGN_KEYS' => 'true',
    'MAIL_MAILER' => 'array',
    'CACHE_STORE' => 'array',
    'SESSION_DRIVER' => 'array',
    'QUEUE_CONNECTION' => 'null',
    'LOG_CHANNEL' => 'null',
    'BCRYPT_ROUNDS' => '4',
    'XLIPA_SYNTHETIC_ONLY' => '1',
];
foreach ($syntheticEnvironment as $name => $value) {
    putenv($name.'='.$value);
    $_ENV[$name] = $_SERVER[$name] = $value;
}
if (file_exists($syntheticEnvironment['APP_CONFIG_CACHE']) || file_exists($syntheticEnvironment['APP_ROUTES_CACHE'])) {
    throw new LogicException('Unexpected cached synthetic configuration.');
}
require dirname(__DIR__).'/vendor/autoload.php';
