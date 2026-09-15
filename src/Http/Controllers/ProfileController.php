<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\View;
use App\Services\CloudinaryMediaService;
use App\Services\MediaCleanupService;

final class ProfileController
{
    public function index(): void
    {
        Auth::requireLogin();
        $stmt = Database::connection()->prepare(
            'SELECT id, name, email, phone, role, avatar_url, avatar_public_id FROM users WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => Auth::id()]);
        $profile = $stmt->fetch();

        if (!$profile) {
            http_response_code(404);
            View::render('errors/404', ['title' => 'Usuário não encontrado']);
            return;
        }

        View::render('panel/profile', [
            'title' => 'Meu perfil',
            'profile' => $profile,
            'cloudinaryConfigured' => (new CloudinaryMediaService())->configured(),
        ]);
    }

    public function uploadAvatar(): void
    {
        Auth::requireLogin();
        Csrf::validate($_POST['_csrf'] ?? null);
        $userId = (int) Auth::id();
        $cloudinary = new CloudinaryMediaService();
        $cleanup = new MediaCleanupService($cloudinary);
        $newPublicId = null;
        $pdo = null;

        try {
            $uploaded = $cloudinary->uploadImage(
                (array) ($_FILES['avatar'] ?? []),
                'agenda-cli/users/' . $userId . '/profile',
                'avatar'
            );
            $newPublicId = $uploaded['public_id'];
            $pdo = Database::connection();
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('SELECT avatar_public_id FROM users WHERE id = :id LIMIT 1 FOR UPDATE');
            $stmt->execute(['id' => $userId]);
            $oldPublicId = $stmt->fetchColumn();
            if ($oldPublicId === false) {
                throw new \RuntimeException('Usuário não encontrado.');
            }

            $pdo->prepare('UPDATE users SET avatar_url = :url, avatar_public_id = :public_id WHERE id = :id')
                ->execute([
                    'url' => $uploaded['secure_url'],
                    'public_id' => $uploaded['public_id'],
                    'id' => $userId,
                ]);
            $pdo->commit();

            $cleanupOk = $cleanup->deleteOrQueue(is_string($oldPublicId) ? $oldPublicId : null);
            flash('success', $cleanupOk ? 'Foto de perfil atualizada.' : 'Foto atualizada. A limpeza da imagem anterior ficou agendada.');
        } catch (\Throwable $exception) {
            if ($pdo instanceof \PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($newPublicId !== null) {
                $cleanup->deleteOrQueue($newPublicId);
            }
            flash('error', $exception instanceof \RuntimeException ? $exception->getMessage() : 'Não foi possível atualizar a foto de perfil.');
        }

        redirect('/painel/perfil');
    }

    public function deleteAvatar(): void
    {
        Auth::requireLogin();
        Csrf::validate($_POST['_csrf'] ?? null);
        $userId = (int) Auth::id();
        $pdo = Database::connection();

        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('SELECT avatar_public_id FROM users WHERE id = :id LIMIT 1 FOR UPDATE');
            $stmt->execute(['id' => $userId]);
            $oldPublicId = $stmt->fetchColumn();
            if ($oldPublicId === false) {
                throw new \RuntimeException('Usuário não encontrado.');
            }
            $pdo->prepare('UPDATE users SET avatar_url = NULL, avatar_public_id = NULL WHERE id = :id')->execute(['id' => $userId]);
            $pdo->commit();

            $cleanupOk = (new MediaCleanupService())->deleteOrQueue(is_string($oldPublicId) ? $oldPublicId : null);
            flash('success', $cleanupOk ? 'Foto de perfil removida.' : 'Foto removida. A exclusão no Cloudinary ficou agendada.');
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            flash('error', $exception instanceof \RuntimeException ? $exception->getMessage() : 'Não foi possível remover a foto de perfil.');
        }

        redirect('/painel/perfil');
    }
}
