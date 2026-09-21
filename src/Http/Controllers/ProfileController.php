<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\View;
use App\Services\CloudinaryMediaService;
use App\Services\MediaCleanupService;
use App\Services\PasswordPolicy;
use DateTimeImmutable;

final class ProfileController
{
    private const BRAZIL_STATES = [
        'AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG',
        'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO',
    ];

    public function index(): void
    {
        Auth::requireLogin();
        $stmt = Database::connection()->prepare(
            'SELECT id, name, email, phone, role, birth_date, city, state, bio, avatar_url, avatar_public_id, created_at '
            . 'FROM users WHERE id = :id LIMIT 1'
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
            'states' => self::BRAZIL_STATES,
            'cloudinaryConfigured' => (new CloudinaryMediaService())->configured(),
        ]);
    }

    public function update(): void
    {
        Auth::requireLogin();
        Csrf::validate($_POST['_csrf'] ?? null);
        $userId = (int) Auth::id();

        $name = trim((string) ($_POST['name'] ?? ''));
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $birthDate = trim((string) ($_POST['birth_date'] ?? ''));
        $city = trim((string) ($_POST['city'] ?? ''));
        $state = strtoupper(trim((string) ($_POST['state'] ?? '')));
        $bio = trim((string) ($_POST['bio'] ?? ''));

        if ($name === '' || strlen($name) > 120) {
            flash('error', 'Informe um nome válido com até 120 caracteres.');
            redirect('/painel/perfil');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
            flash('error', 'Informe um e-mail válido.');
            redirect('/painel/perfil');
        }
        if (strlen($phone) > 30 || strlen($city) > 100 || strlen($bio) > 500) {
            flash('error', 'Revise telefone, cidade e bio. Um dos campos excede o limite permitido.');
            redirect('/painel/perfil');
        }
        if ($state !== '' && !in_array($state, self::BRAZIL_STATES, true)) {
            flash('error', 'Selecione uma UF válida.');
            redirect('/painel/perfil');
        }

        $birthDateValue = null;
        if ($birthDate !== '') {
            $parsedBirthDate = DateTimeImmutable::createFromFormat('!Y-m-d', $birthDate);
            $minimumDate = new DateTimeImmutable('1900-01-01');
            $today = new DateTimeImmutable('today');
            if (!$parsedBirthDate || $parsedBirthDate->format('Y-m-d') !== $birthDate || $parsedBirthDate < $minimumDate || $parsedBirthDate > $today) {
                flash('error', 'Informe uma data de nascimento válida.');
                redirect('/painel/perfil');
            }
            $birthDateValue = $birthDate;
        }

        $pdo = Database::connection();
        $duplicate = $pdo->prepare('SELECT id FROM users WHERE email = :email AND id <> :id LIMIT 1');
        $duplicate->execute(['email' => $email, 'id' => $userId]);
        if ($duplicate->fetchColumn()) {
            flash('error', 'Este e-mail já está sendo usado por outra conta.');
            redirect('/painel/perfil');
        }

        $stmt = $pdo->prepare(
            'UPDATE users SET name = :name, email = :email, phone = :phone, birth_date = :birth_date, '
            . 'city = :city, state = :state, bio = :bio WHERE id = :id'
        );
        $stmt->execute([
            'name' => $name,
            'email' => $email,
            'phone' => $phone !== '' ? $phone : null,
            'birth_date' => $birthDateValue,
            'city' => $city !== '' ? $city : null,
            'state' => $state !== '' ? $state : null,
            'bio' => $bio !== '' ? $bio : null,
            'id' => $userId,
        ]);

        flash('success', 'Dados pessoais atualizados.');
        redirect('/painel/perfil');
    }

    public function passwordForm(): void
    {
        Auth::requireLogin();
        View::render('panel/password', ['title' => 'Alterar senha']);
    }

    public function updatePassword(): void
    {
        Auth::requireLogin();
        Csrf::validate($_POST['_csrf'] ?? null);

        $currentPassword = (string) ($_POST['current_password'] ?? '');
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmation = (string) ($_POST['new_password_confirmation'] ?? '');
        $policyErrors = (new PasswordPolicy())->errors($newPassword);

        if ($newPassword !== $confirmation) {
            flash('error', 'A confirmação da nova senha não confere.');
            redirect('/painel/perfil/senha');
        }
        if ($policyErrors !== []) {
            flash('error', $policyErrors[0]);
            redirect('/painel/perfil/senha');
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = :id AND status = "active" LIMIT 1');
        $stmt->execute(['id' => Auth::id()]);
        $hash = $stmt->fetchColumn();

        if (!is_string($hash) || !password_verify($currentPassword, $hash)) {
            flash('error', 'A senha atual está incorreta.');
            redirect('/painel/perfil/senha');
        }
        if (password_verify($newPassword, $hash)) {
            flash('error', 'A nova senha deve ser diferente da senha atual.');
            redirect('/painel/perfil/senha');
        }

        $pdo->prepare('UPDATE users SET password_hash = :password WHERE id = :id')
            ->execute([
                'password' => password_hash($newPassword, PASSWORD_DEFAULT),
                'id' => Auth::id(),
            ]);

        session_regenerate_id(true);
        flash('success', 'Senha alterada com sucesso.');
        redirect('/painel/perfil');
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
