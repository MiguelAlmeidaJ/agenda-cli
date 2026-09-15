<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\TenantContext;
use App\Core\View;
use App\Services\CloudinaryMediaService;
use App\Services\MediaCleanupService;

final class AppearanceController
{
    public function index(): void
    {
        Auth::requireRole(['owner']);
        $establishmentId = TenantContext::requireEstablishmentId();
        $stmt = Database::connection()->prepare(
            'SELECT id, name, slug, logo_url, logo_public_id, cover_url, cover_public_id FROM establishments WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $establishmentId]);
        $establishment = $stmt->fetch();

        if (!$establishment) {
            http_response_code(404);
            View::render('errors/404', ['title' => 'Estabelecimento não encontrado']);
            return;
        }

        View::render('panel/appearance', [
            'title' => 'Aparência',
            'establishment' => $establishment,
            'cloudinaryConfigured' => (new CloudinaryMediaService())->configured(),
        ]);
    }

    public function uploadLogo(): void
    {
        $this->replaceImage('logo_url', 'logo_public_id', 'logo', 'logo');
    }

    public function uploadCover(): void
    {
        $this->replaceImage('cover_url', 'cover_public_id', 'cover', 'cover');
    }

    public function deleteLogo(): void
    {
        $this->removeImage('logo_url', 'logo_public_id', 'Logo removido.');
    }

    public function deleteCover(): void
    {
        $this->removeImage('cover_url', 'cover_public_id', 'Capa removida.');
    }

    private function replaceImage(string $urlColumn, string $publicIdColumn, string $fileField, string $folder): void
    {
        Auth::requireRole(['owner']);
        Csrf::validate($_POST['_csrf'] ?? null);
        $establishmentId = TenantContext::requireEstablishmentId();
        $cloudinary = new CloudinaryMediaService();
        $cleanup = new MediaCleanupService($cloudinary);
        $newPublicId = null;
        $pdo = null;

        try {
            $uploaded = $cloudinary->uploadImage(
                (array) ($_FILES[$fileField] ?? []),
                'agenda-cli/establishments/' . $establishmentId . '/' . $folder,
                $folder
            );
            $newPublicId = $uploaded['public_id'];
            $pdo = Database::connection();
            $pdo->beginTransaction();

            if ($fileField === 'logo') {
                $select = $pdo->prepare('SELECT logo_public_id FROM establishments WHERE id = :id LIMIT 1 FOR UPDATE');
                $update = $pdo->prepare('UPDATE establishments SET logo_url = :url, logo_public_id = :public_id WHERE id = :id');
            } else {
                $select = $pdo->prepare('SELECT cover_public_id FROM establishments WHERE id = :id LIMIT 1 FOR UPDATE');
                $update = $pdo->prepare('UPDATE establishments SET cover_url = :url, cover_public_id = :public_id WHERE id = :id');
            }

            $select->execute(['id' => $establishmentId]);
            $oldPublicId = $select->fetchColumn();
            if ($oldPublicId === false) {
                throw new \RuntimeException('Estabelecimento não encontrado.');
            }

            $update->execute([
                'url' => $uploaded['secure_url'],
                'public_id' => $uploaded['public_id'],
                'id' => $establishmentId,
            ]);
            $pdo->commit();

            $cleanupOk = $cleanup->deleteOrQueue(is_string($oldPublicId) ? $oldPublicId : null);
            $label = $fileField === 'logo' ? 'Logo atualizado.' : 'Capa atualizada.';
            flash('success', $cleanupOk ? $label : $label . ' A limpeza da imagem anterior ficou agendada.');
        } catch (\Throwable $exception) {
            if ($pdo instanceof \PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($newPublicId !== null) {
                $cleanup->deleteOrQueue($newPublicId);
            }
            flash('error', $exception instanceof \RuntimeException ? $exception->getMessage() : 'Não foi possível atualizar a imagem.');
        }

        redirect('/painel/aparencia');
    }

    private function removeImage(string $urlColumn, string $publicIdColumn, string $message): void
    {
        Auth::requireRole(['owner']);
        Csrf::validate($_POST['_csrf'] ?? null);
        $establishmentId = TenantContext::requireEstablishmentId();
        $pdo = Database::connection();

        try {
            $pdo->beginTransaction();
            if ($publicIdColumn === 'logo_public_id') {
                $select = $pdo->prepare('SELECT logo_public_id FROM establishments WHERE id = :id LIMIT 1 FOR UPDATE');
                $update = $pdo->prepare('UPDATE establishments SET logo_url = NULL, logo_public_id = NULL WHERE id = :id');
            } else {
                $select = $pdo->prepare('SELECT cover_public_id FROM establishments WHERE id = :id LIMIT 1 FOR UPDATE');
                $update = $pdo->prepare('UPDATE establishments SET cover_url = NULL, cover_public_id = NULL WHERE id = :id');
            }
            $select->execute(['id' => $establishmentId]);
            $oldPublicId = $select->fetchColumn();
            if ($oldPublicId === false) {
                throw new \RuntimeException('Estabelecimento não encontrado.');
            }
            $update->execute(['id' => $establishmentId]);
            $pdo->commit();

            $cleanupOk = (new MediaCleanupService())->deleteOrQueue(is_string($oldPublicId) ? $oldPublicId : null);
            flash('success', $cleanupOk ? $message : $message . ' A exclusão no Cloudinary ficou agendada.');
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            flash('error', $exception instanceof \RuntimeException ? $exception->getMessage() : 'Não foi possível remover a imagem.');
        }

        redirect('/painel/aparencia');
    }
}
