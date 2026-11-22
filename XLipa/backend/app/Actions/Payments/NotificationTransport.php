<?php

namespace App\Actions\Payments;

/** A queue acknowledgement is not delivery. Adapters must honor the stable delivery id. */
interface NotificationTransport
{
    /** @return array{status:string,receipt:?string} */
    public function send(string $channel, string $recipient, string $subject, string $message, string $deliveryId): array;
}
