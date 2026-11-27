<?php

// Dedicated cli-server router. Operational public/index.php never loads this file.
require __DIR__.'/../backend/vendor/autoload.php';

use App\Staging\StagingIdentity;
use App\Staging\StagingRuntime;
use Illuminate\Http\Request;

try {
    $context = StagingIdentity::admit((string) getenv('XLIPA_STAGING_RUN'), PHP_SAPI, $_SERVER['HTTP_HOST'] ?? '', $_SERVER['REMOTE_ADDR'] ?? '');
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    if (is_string($path) && preg_match('~^/assets/[A-Za-z0-9/_.-]+\.(css|js|png|jpg|jpeg|svg|webp|woff2?|ico)$~D', $path)) {
        StagingIdentity::check(! str_contains($path, '..') && in_array($_SERVER['REQUEST_METHOD'], ['GET', 'HEAD'], true));
        $asset = StagingIdentity::path(str_replace('\\', '/', realpath(__DIR__.'/../backend/public')).$path);
        $types = ['css' => 'text/css', 'js' => 'application/javascript', 'svg' => 'image/svg+xml', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp', 'ico' => 'image/x-icon', 'woff' => 'font/woff', 'woff2' => 'font/woff2'];
        header('Content-Type: '.$types[pathinfo($asset, PATHINFO_EXTENSION)]);
        header('X-Content-Type-Options: nosniff');
        if ($_SERVER['REQUEST_METHOD'] !== 'HEAD') {
            readfile($asset);
        }
    } else {
        StagingRuntime::application($context)->handleRequest(Request::capture());
    }
} catch (Throwable $error) {
    error_log('Staging denied: '.$error::class.' at '.basename($error->getFile()).':'.$error->getLine());
    if (! headers_sent()) {
        http_response_code(503);
        header('Cache-Control: no-store');
    }
    echo 'Fictional staging is unavailable. No operational fallback.';
}
