<?php

namespace App\Staging;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class StagingRuntime
{
    public static function application(array $context): Application
    {
        $root = $context['root'];
        $environment = [
            'APP_ENV' => 'staging', 'APP_DEBUG' => 'false', 'APP_URL' => StagingIdentity::ORIGIN,
            'APP_KEY' => StagingIdentity::read($root.'/app-key.json')['key'], 'APP_PREVIOUS_KEYS' => '',
            'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $root.'/workflow.sqlite', 'DB_URL' => '',
            'APP_CONFIG_CACHE' => $root.'/absent-config.php', 'APP_ROUTES_CACHE' => $root.'/absent-routes.php',
            'APP_EVENTS_CACHE' => $root.'/absent-events.php', 'APP_SERVICES_CACHE' => $root.'/services.php',
            'APP_PACKAGES_CACHE' => $root.'/packages.php', 'MAIL_MAILER' => 'array', 'LOG_CHANNEL' => 'null',
            'CACHE_STORE' => 'file', 'SESSION_DRIVER' => 'file', 'QUEUE_CONNECTION' => 'null',
            'XLIPA_SYNTHETIC_ONLY' => '0',
        ];
        foreach (['absent-config.php', 'absent-routes.php', 'absent-events.php', '.env', '.env.staging'] as $absent) {
            StagingIdentity::check(! file_exists($root.'/'.$absent));
        }
        foreach ($environment as $key => $value) {
            putenv($key.'='.$value);
            $_ENV[$key] = $_SERVER[$key] = $value;
        }
        $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
        $app->addAbsoluteCachePathPrefix(substr($root, 0, 3));
        $app->useEnvironmentPath($root)->loadEnvironmentFrom('.env');
        $app->useStoragePath($root.'/storage');
        $app->instance('request', PHP_SAPI === 'cli-server' ? Request::capture() : Request::create(StagingIdentity::ORIGIN));
        $app->instance('staging.context', $context);
        $app->register(StagingServiceProvider::class);
        $app->booting(function () use ($app, $root, $context): void {
            $config = $app->make('config');
            $config->set('database', ['default' => 'sqlite', 'migrations' => ['table' => 'migrations', 'update_date_on_publish' => false], 'connections' => ['sqlite' => [
                'driver' => 'sqlite', 'database' => $root.'/workflow.sqlite', 'prefix' => '',
                'foreign_key_constraints' => true, 'busy_timeout' => 2000, 'transaction_mode' => 'DEFERRED',
            ]]]);
            $config->set('workflow_schema.expected_profile', ($context['workflow_profile'] ?? null) === WorkflowProvisioner::VERSION ? 'b32c+se1+bnc1' : 'foundation');
            $config->set('session', [...$config->get('session'), 'driver' => 'file', 'files' => $root.'/storage/framework/sessions',
                'cookie' => 'xlipa_r21_'.substr($context['runtime'], 4, 12), 'domain' => null, 'path' => '/',
                'secure' => false, 'http_only' => true, 'same_site' => 'strict', 'lifetime' => 30]);
            $config->set('cache.default', 'file');
            $config->set('cache.stores.file', ['driver' => 'file', 'path' => $root.'/storage/framework/cache', 'lock_path' => $root.'/storage/framework/cache']);
            $config->set('view.compiled', $root.'/storage/framework/views');
            $config->set('mail.default', 'array');
            $config->set('mail.mailers', ['array' => ['transport' => 'array']]);
            $config->set('queue.default', 'null');
            $config->set('queue.connections', ['null' => ['driver' => 'null']]);
            $config->set('logging.default', 'null');
            $config->set('filesystems.disks', ['local' => ['driver' => 'local', 'root' => $root.'/storage']]);
        });
        $kernel = $app->make(Kernel::class);
        $kernel->bootstrap();
        DB::statement('PRAGMA recursive_triggers=ON');
        $kernel->prependMiddleware(StagingBoundary::class);

        return $app;
    }
}
