<?php

namespace App\Actions\Payments;

/** Queued/accepted acknowledgements are not delivery. Adapters must deduplicate the stable delivery id. */
interface NotificationTransport
{
    /** @return array{status:'queued'|'accepted'|'delivered'|'failed',receipt:?string} */
    public function send(string $channel, string $recipient, string $subject, string $message, string $deliveryId): array;
}
