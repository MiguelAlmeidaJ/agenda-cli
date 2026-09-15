<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class MediaCleanupService
{
    public function __construct(private readonly ?CloudinaryMediaService $cloudinary = null)
    {
    }

    public function deleteOrQueue(?string $publicId): bool
    {
        $publicId = trim((string) $publicId);
        if ($publicId === '') {
            return true;
        }

        $cloudinary = $this->cloudinary ?? new CloudinaryMediaService();
        $result = $cloudinary->destroy($publicId);
        if (($result['success'] ?? false) === true) {
            $this->removeQueued($publicId);
            return true;
        }

        $this->queue($publicId, (string) ($result['error'] ?? 'Falha desconhecida ao excluir mídia.'));
        return false;
    }

    /** @return array{deleted:int,failed:int,skipped:int} */
    public function processPending(int $limit = 50): array
    {
        $cloudinary = $this->cloudinary ?? new CloudinaryMediaService();
        $limit = max(1, min(200, $limit));
        if (!$cloudinary->configured()) {
            return ['deleted' => 0, 'failed' => 0, 'skipped' => 1];
        }

        $pdo = Database::connection();
        $stmt = $pdo->query(
            'SELECT id, public_id, attempts FROM media_cleanup_queue '
            . 'WHERE attempts < 10 AND next_attempt_at <= NOW() '
            . 'ORDER BY next_attempt_at, id LIMIT ' . $limit
        );

        $deleted = 0;
        $failed = 0;
        foreach ($stmt->fetchAll() as $item) {
            $result = $cloudinary->destroy((string) $item['public_id']);
            if (($result['success'] ?? false) === true) {
                $pdo->prepare('DELETE FROM media_cleanup_queue WHERE id = :id')->execute(['id' => $item['id']]);
                $deleted++;
                continue;
            }

            $error = substr((string) ($result['error'] ?? 'Falha desconhecida.'), 0, 255);
            $pdo->prepare(
                'UPDATE media_cleanup_queue SET attempts = attempts + 1, last_error = :error, '
                . 'next_attempt_at = DATE_ADD(NOW(), INTERVAL 30 MINUTE) WHERE id = :id'
            )->execute(['error' => $error, 'id' => $item['id']]);
            $failed++;
        }

        return ['deleted' => $deleted, 'failed' => $failed, 'skipped' => 0];
    }

    private function queue(string $publicId, string $error): void
    {
        try {
            Database::connection()->prepare(
                'INSERT INTO media_cleanup_queue (public_id, attempts, last_error, next_attempt_at) '
                . 'VALUES (:public_id, 1, :error, DATE_ADD(NOW(), INTERVAL 10 MINUTE)) '
                . 'ON DUPLICATE KEY UPDATE last_error = VALUES(last_error), '
                . 'next_attempt_at = LEAST(next_attempt_at, VALUES(next_attempt_at)), updated_at = CURRENT_TIMESTAMP'
            )->execute([
                'public_id' => $publicId,
                'error' => substr($error, 0, 255),
            ]);
        } catch (\Throwable $exception) {
            error_log('Agenda CLI media cleanup queue: ' . $exception->getMessage());
        }
    }

    private function removeQueued(string $publicId): void
    {
        try {
            Database::connection()->prepare('DELETE FROM media_cleanup_queue WHERE public_id = :public_id')
                ->execute(['public_id' => $publicId]);
        } catch (\Throwable) {
        }
    }
}
