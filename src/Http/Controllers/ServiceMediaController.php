<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\TenantContext;
use App\Services\CloudinaryMediaService;
use App\Services\MediaCleanupService;

final class ServiceMediaController
{
    public function upload(string $id): void
    {
        Auth::requireRole(['owner']);
        Csrf::validate($_POST['_csrf'] ?? null);
        $establishmentId = TenantContext::requireEstablishmentId();
        $serviceId = (int) $id;
        if ($serviceId <= 0) {
            flash('error', 'Serviço inválido.');
            redirect('/painel/servicos');
        }

        $cloudinary = new CloudinaryMediaService();
        $cleanup = new MediaCleanupService($cloudinary);
        $newPublicId = null;
        $pdo = null;

        try {
            $uploaded = $cloudinary->uploadImage(
                (array) ($_FILES['image'] ?? []),
                'agenda-cli/establishments/' . $establishmentId . '/services/' . $serviceId,
                'service'
            );
            $newPublicId = $uploaded['public_id'];
            $pdo = Database::connection();
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                'SELECT image_public_id FROM services WHERE id = :service AND establishment_id = :establishment LIMIT 1 FOR UPDATE'
            );
            $stmt->execute(['service' => $serviceId, 'establishment' => $establishmentId]);
            $oldPublicId = $stmt->fetchColumn();
            if ($oldPublicId === false) {
                throw new \RuntimeException('Serviço não encontrado.');
            }

            $pdo->prepare(
                'UPDATE services SET image_url = :url, image_public_id = :public_id '
                . 'WHERE id = :service AND establishment_id = :establishment'
            )->execute([
                'url' => $uploaded['secure_url'],
                'public_id' => $uploaded['public_id'],
                'service' => $serviceId,
                'establishment' => $establishmentId,
            ]);
            $pdo->commit();

            $cleanupOk = $cleanup->deleteOrQueue(is_string($oldPublicId) ? $oldPublicId : null);
            flash('success', $cleanupOk ? 'Imagem do serviço atualizada.' : 'Imagem atualizada. A limpeza da anterior ficou agendada.');
        } catch (\Throwable $exception) {
            if ($pdo instanceof \PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($newPublicId !== null) {
                $cleanup->deleteOrQueue($newPublicId);
            }
            flash('error', $exception instanceof \RuntimeException ? $exception->getMessage() : 'Não foi possível atualizar a imagem do serviço.');
        }

        redirect('/painel/servicos');
    }

    public function delete(string $id): void
    {
        Auth::requireRole(['owner']);
        Csrf::validate($_POST['_csrf'] ?? null);
        $establishmentId = TenantContext::requireEstablishmentId();
        $serviceId = (int) $id;
        $pdo = Database::connection();

        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare(
                'SELECT image_public_id FROM services WHERE id = :service AND establishment_id = :establishment LIMIT 1 FOR UPDATE'
            );
            $stmt->execute(['service' => $serviceId, 'establishment' => $establishmentId]);
            $oldPublicId = $stmt->fetchColumn();
            if ($oldPublicId === false) {
                throw new \RuntimeException('Serviço não encontrado.');
            }

            $pdo->prepare(
                'UPDATE services SET image_url = NULL, image_public_id = NULL '
                . 'WHERE id = :service AND establishment_id = :establishment'
            )->execute(['service' => $serviceId, 'establishment' => $establishmentId]);
            $pdo->commit();

            $cleanupOk = (new MediaCleanupService())->deleteOrQueue(is_string($oldPublicId) ? $oldPublicId : null);
            flash('success', $cleanupOk ? 'Imagem do serviço removida.' : 'Imagem removida. A exclusão no Cloudinary ficou agendada.');
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            flash('error', $exception instanceof \RuntimeException ? $exception->getMessage() : 'Não foi possível remover a imagem do serviço.');
        }

        redirect('/painel/servicos');
    }
}
