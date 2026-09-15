<?php

declare(strict_types=1);

namespace App\Services;

final class CloudinaryMediaService
{
    private const MAX_BYTES = 5 * 1024 * 1024;
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
        'image/avif',
    ];

    public function configured(): bool
    {
        return trim((string) env('CLOUDINARY_CLOUD_NAME', '')) !== ''
            && trim((string) env('CLOUDINARY_API_KEY', '')) !== ''
            && trim((string) env('CLOUDINARY_API_SECRET', '')) !== ''
            && function_exists('curl_init');
    }

    /** @return array{secure_url:string,public_id:string,width:int,height:int,bytes:int} */
    public function uploadImage(array $file, string $folder, string $label): array
    {
        $this->assertConfigured();
        $this->validateImage($file);

        $folder = trim($folder, '/');
        $safeLabel = preg_replace('/[^a-z0-9_-]+/i', '-', trim($label)) ?: 'image';
        $publicId = $folder . '/' . trim($safeLabel, '-') . '-' . bin2hex(random_bytes(10));
        $timestamp = time();
        $params = [
            'asset_folder' => $folder,
            'public_id' => $publicId,
            'timestamp' => $timestamp,
        ];

        $payload = $params + [
            'api_key' => $this->apiKey(),
            'signature' => $this->signature($params),
            'file' => new \CURLFile(
                (string) $file['tmp_name'],
                $this->detectMimeType((string) $file['tmp_name']),
                basename((string) ($file['name'] ?? 'image'))
            ),
        ];

        $response = $this->post('image/upload', $payload);
        if (!$response['ok']) {
            throw new \RuntimeException($response['error'] ?: 'Não foi possível enviar a imagem para o Cloudinary.');
        }

        $body = $response['body'];
        $secureUrl = is_array($body) ? trim((string) ($body['secure_url'] ?? '')) : '';
        $returnedPublicId = is_array($body) ? trim((string) ($body['public_id'] ?? '')) : '';
        if ($secureUrl === '' || $returnedPublicId === '') {
            throw new \RuntimeException('O Cloudinary não retornou os dados esperados do upload.');
        }

        return [
            'secure_url' => $secureUrl,
            'public_id' => $returnedPublicId,
            'width' => (int) ($body['width'] ?? 0),
            'height' => (int) ($body['height'] ?? 0),
            'bytes' => (int) ($body['bytes'] ?? 0),
        ];
    }

    /** @return array{success:bool,error:?string} */
    public function destroy(string $publicId): array
    {
        $publicId = trim($publicId);
        if ($publicId === '') {
            return ['success' => true, 'error' => null];
        }

        if (!$this->configured()) {
            return ['success' => false, 'error' => 'Cloudinary não configurado no servidor.'];
        }

        $timestamp = time();
        $params = [
            'invalidate' => 'true',
            'public_id' => $publicId,
            'timestamp' => $timestamp,
        ];
        $response = $this->post('image/destroy', $params + [
            'api_key' => $this->apiKey(),
            'signature' => $this->signature($params),
        ]);

        if (!$response['ok']) {
            return ['success' => false, 'error' => $response['error'] ?: 'Falha ao excluir imagem no Cloudinary.'];
        }

        $result = is_array($response['body']) ? (string) ($response['body']['result'] ?? '') : '';
        if (in_array($result, ['ok', 'not found'], true)) {
            return ['success' => true, 'error' => null];
        }

        return ['success' => false, 'error' => 'O Cloudinary não confirmou a exclusão do arquivo.'];
    }

    private function validateImage(array $file): void
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            throw new \RuntimeException('Selecione uma imagem para enviar.');
        }
        if ($error !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('O upload da imagem não foi concluído pelo servidor.');
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        $size = (int) ($file['size'] ?? 0);
        if ($tmpName === '' || !is_file($tmpName)) {
            throw new \RuntimeException('Arquivo temporário de upload inválido.');
        }
        if ($size <= 0 || $size > self::MAX_BYTES) {
            throw new \RuntimeException('A imagem deve ter no máximo 5 MB.');
        }

        $mime = $this->detectMimeType($tmpName);
        if (!in_array($mime, self::ALLOWED_MIME_TYPES, true)) {
            throw new \RuntimeException('Formato inválido. Use JPG, PNG, WebP, GIF ou AVIF.');
        }
        if (@getimagesize($tmpName) === false) {
            throw new \RuntimeException('O arquivo enviado não é uma imagem válida.');
        }
    }

    private function detectMimeType(string $path): string
    {
        if (!function_exists('finfo_open')) {
            throw new \RuntimeException('A extensão fileinfo do PHP é necessária para validar imagens.');
        }
        $info = finfo_open(FILEINFO_MIME_TYPE);
        if ($info === false) {
            throw new \RuntimeException('Não foi possível validar o tipo da imagem.');
        }
        $mime = (string) finfo_file($info, $path);
        finfo_close($info);
        return $mime;
    }

    /** @return array{ok:bool,body:?array,error:?string} */
    private function post(string $action, array $fields): array
    {
        $url = 'https://api.cloudinary.com/v1_1/' . rawurlencode($this->cloudName()) . '/' . ltrim($action, '/');
        $request = curl_init($url);
        if ($request === false) {
            return ['ok' => false, 'body' => null, 'error' => 'Não foi possível iniciar a conexão com o Cloudinary.'];
        }

        curl_setopt_array($request, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 35,
            CURLOPT_POSTFIELDS => $fields,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_USERAGENT => 'AgendaCLI/1.0',
        ]);

        $raw = curl_exec($request);
        $curlError = curl_error($request);
        $status = (int) curl_getinfo($request, CURLINFO_HTTP_CODE);
        curl_close($request);

        if ($raw === false) {
            return ['ok' => false, 'body' => null, 'error' => $curlError !== '' ? $curlError : 'Falha de comunicação com o Cloudinary.'];
        }

        $body = json_decode((string) $raw, true);
        $error = null;
        if (is_array($body)) {
            $error = (string) (($body['error']['message'] ?? null) ?: ($body['message'] ?? ''));
        }

        return [
            'ok' => $status >= 200 && $status < 300,
            'body' => is_array($body) ? $body : null,
            'error' => $error !== '' ? $error : ($status >= 200 && $status < 300 ? null : 'Cloudinary respondeu com HTTP ' . $status . '.'),
        ];
    }

    private function signature(array $params): string
    {
        ksort($params);
        $parts = [];
        foreach ($params as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $parts[] = $key . '=' . $value;
        }
        return sha1(implode('&', $parts) . $this->apiSecret());
    }

    private function assertConfigured(): void
    {
        if (!$this->configured()) {
            throw new \RuntimeException('Configure CLOUDINARY_CLOUD_NAME, CLOUDINARY_API_KEY e CLOUDINARY_API_SECRET no .env e habilite cURL no PHP.');
        }
    }

    private function cloudName(): string
    {
        return trim((string) env('CLOUDINARY_CLOUD_NAME', ''));
    }

    private function apiKey(): string
    {
        return trim((string) env('CLOUDINARY_API_KEY', ''));
    }

    private function apiSecret(): string
    {
        return trim((string) env('CLOUDINARY_API_SECRET', ''));
    }
}
