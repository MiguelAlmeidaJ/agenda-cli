<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Support\HttpClient;

final class EvolutionGoWhatsappGateway implements WhatsappGatewayInterface
{
    public function send(string $recipient, string $message): array
    {
        $baseUrl = rtrim((string) env('EVOLUTION_GO_BASE_URL', ''), '/');
        $headerFile = trim((string) env('EVOLUTION_GO_HEADER_FILE', ''));
        if ($baseUrl === '' || !str_starts_with($baseUrl, 'https://') || $headerFile === '' || !is_readable($headerFile)) {
            return ['success' => false, 'error' => 'Configure a URL e o arquivo protegido de autenticação da Evolution GO.'];
        }

        $headers = [];
        foreach (file($headerFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            [$name, $value] = array_pad(explode(':', $line, 2), 2, '');
            if (trim($name) !== '' && trim($value) !== '') {
                $headers[trim($name)] = trim($value);
            }
        }
        if ($headers === []) {
            return ['success' => false, 'error' => 'O arquivo de autenticação da Evolution GO está vazio.'];
        }

        $response = (new HttpClient())->postJson(
            $baseUrl . '/send/text',
            [
                'number' => preg_replace('/\D+/', '', $recipient) ?? '',
                'text' => $message,
                'id' => 'agenda-' . bin2hex(random_bytes(8)),
            ],
            $headers,
        );

        if (!$response['ok']) {
            $body = is_array($response['body'] ?? null) ? $response['body'] : [];
            $error = (string) ($body['error'] ?? $body['message'] ?? $response['error'] ?? 'Falha no envio.');
            return ['success' => false, 'error' => $error];
        }

        $body = is_array($response['body'] ?? null) ? $response['body'] : [];
        $messageId = (string) ($body['data']['Info']['ID'] ?? $body['data']['messageId'] ?? '');
        return ['success' => true, 'message_id' => $messageId];
    }
}
