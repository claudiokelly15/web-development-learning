<?php

namespace App\Actions\Payments;

use LogicException;

final class DeniedNotificationTransport implements NotificationTransport
{
    public function send(string $channel, string $recipient, string $subject, string $message, string $deliveryId): array
    {
        throw new LogicException('External communications are not activated.');
    }
}
