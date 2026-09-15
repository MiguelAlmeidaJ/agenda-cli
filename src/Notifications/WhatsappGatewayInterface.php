<?php

declare(strict_types=1);

namespace App\Notifications;

interface WhatsappGatewayInterface
{
    /** @return array{success: bool, message_id?: string, error?: string} */
    public function send(string $recipient, string $message): array;
}
