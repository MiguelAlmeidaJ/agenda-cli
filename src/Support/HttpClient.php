<?php

declare(strict_types=1);

namespace App\Support;

final class HttpClient
{
    /** @param array<string,string> $headers */
    public function postJson(string $url, array $payload, array $headers = []): array
    {
        if (!function_exists('curl_init')) {
            return ['ok' => false, 'status' => 0, 'body' => null, 'error' => 'A extensão cURL do PHP não está disponível.'];
        }

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return ['ok' => false, 'status' => 0, 'body' => null, 'error' => 'Não foi possível serializar a requisição.'];
        }

        $headerLines = ['Content-Type: application/json', 'Accept: application/json'];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }

        $request = curl_init($url);
        curl_setopt_array($request, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_POSTFIELDS => $json,
        ]);
        $response = curl_exec($request);
        $error = curl_error($request);
        $status = (int) curl_getinfo($request, CURLINFO_HTTP_CODE);
        curl_close($request);

        if ($response === false) {
            return ['ok' => false, 'status' => $status, 'body' => null, 'error' => $error !== '' ? $error : 'Falha na requisição HTTP.'];
        }

        $decoded = json_decode((string) $response, true);
        return [
            'ok' => $status >= 200 && $status < 300,
            'status' => $status,
            'body' => is_array($decoded) ? $decoded : null,
            'error' => null,
        ];
    }
}
