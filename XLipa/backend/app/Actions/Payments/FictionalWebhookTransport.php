<?php

namespace App\Actions\Payments;

/** Records fictional deliveries only. No sockets, HTTP client, redirects or provider calls. */
final class FictionalWebhookTransport
{
    public array $requests = [];

    public int $status = 204;

    public function send(string $url, string $address, string $body, array $headers): int
    {
        abort_unless(PHP_SAPI === 'cli' && getenv('XLIPA_SYNTHETIC_ONLY') === '1' && app()->environment('testing'), 503);
        $this->requests[] = compact('url', 'address', 'body', 'headers');

        return $this->status;
    }
}
