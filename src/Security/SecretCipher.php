<?php

declare(strict_types=1);

namespace App\Security;

final class SecretCipher
{
    public static function encrypt(string $value): string
    {
        $key = self::key();
        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($value, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($ciphertext === false) {
            throw new \RuntimeException('Não foi possível proteger a credencial.');
        }

        return base64_encode(json_encode([
            'iv' => base64_encode($iv),
            'tag' => base64_encode($tag),
            'data' => base64_encode($ciphertext),
        ], JSON_THROW_ON_ERROR));
    }

    public static function decrypt(?string $payload): string
    {
        if ($payload === null || trim($payload) === '') {
            return '';
        }

        try {
            $decoded = json_decode((string) base64_decode($payload, true), true, 512, JSON_THROW_ON_ERROR);
            $iv = base64_decode((string) ($decoded['iv'] ?? ''), true);
            $tag = base64_decode((string) ($decoded['tag'] ?? ''), true);
            $data = base64_decode((string) ($decoded['data'] ?? ''), true);
            if ($iv === false || $tag === false || $data === false) {
                throw new \RuntimeException('Credencial inválida.');
            }
            $plain = openssl_decrypt($data, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag);
            if ($plain === false) {
                throw new \RuntimeException('Não foi possível abrir a credencial.');
            }
            return $plain;
        } catch (\Throwable $exception) {
            throw new \RuntimeException('Não foi possível ler a credencial protegida.', 0, $exception);
        }
    }

    private static function key(): string
    {
        $appKey = trim((string) env('APP_KEY', ''));
        if (strlen($appKey) < 32) {
            throw new \RuntimeException('Defina APP_KEY no .env com pelo menos 32 caracteres antes de salvar credenciais.');
        }
        return hash('sha256', $appKey, true);
    }
}
