<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\TenantContext;
use App\Core\View;
use DateTimeImmutable;
use DateTimeZone;

final class TeamController
{
    public function index(): void
    {
        Auth::requireRole(['owner']);
        $establishmentId = TenantContext::requireEstablishmentId();
        $pdo = Database::connection();

        $ownerStmt = $pdo->prepare(
            'SELECT u.id, u.name, u.email, u.phone, u.avatar_url, 1 AS active, "owner" AS member_role, '
            . 'COUNT(DISTINCT s.id) AS service_count '
            . 'FROM establishments e JOIN users u ON u.id = e.owner_user_id '
            . 'LEFT JOIN employee_services es ON es.employee_user_id = u.id '
            . 'LEFT JOIN services s ON s.id = es.service_id AND s.establishment_id = e.id '
            . 'WHERE e.id = :establishment GROUP BY u.id LIMIT 1'
        );
        $ownerStmt->execute(['establishment' => $establishmentId]);
        $owner = $ownerStmt->fetch();

        $employees = $pdo->prepare(
            'SELECT u.id, u.name, u.email, u.phone, u.avatar_url, eu.active, "employee" AS member_role, '
            . 'COUNT(DISTINCT s.id) AS service_count '
            . 'FROM establishment_users eu JOIN users u ON u.id = eu.user_id '
            . 'LEFT JOIN employee_services es ON es.employee_user_id = u.id '
            . 'LEFT JOIN services s ON s.id = es.service_id AND s.establishment_id = eu.establishment_id '
            . 'WHERE eu.establishment_id = :establishment AND eu.role = "employee" '
            . 'GROUP BY u.id, eu.active ORDER BY eu.active DESC, u.name'
        );
        $employees->execute(['establishment' => $establishmentId]);

        View::render('panel/team', [
            'title' => 'Equipe',
            'owner' => $owner ?: null,
            'employees' => $employees->fetchAll(),
        ]);
    }

    public function store(): void
    {
        Auth::requireRole(['owner']);
        Csrf::validate($_POST['_csrf'] ?? null);
        $establishmentId = TenantContext::requireEstablishmentId();

        $name = trim((string) ($_POST['name'] ?? ''));
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Informe nome e e-mail válidos.');
            redirect('/painel/equipe');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $find = $pdo->prepare('SELECT id, role, status FROM users WHERE email = :email LIMIT 1 FOR UPDATE');
            $find->execute(['email' => $email]);
            $existing = $find->fetch();

            if ($existing) {
                if (($existing['role'] ?? null) !== 'employee') {
                    throw new \RuntimeException('Este e-mail já pertence a uma conta com outro perfil.');
                }
                if (($existing['status'] ?? null) !== 'active') {
                    throw new \RuntimeException('A conta deste profissional está inativa.');
                }
                $userId = (int) $existing['id'];

                $otherTenant = $pdo->prepare(
                    'SELECT establishment_id FROM establishment_users '
                    . 'WHERE user_id = :user AND role = "employee" AND establishment_id <> :establishment LIMIT 1'
                );
                $otherTenant->execute(['user' => $userId, 'establishment' => $establishmentId]);
                if ($otherTenant->fetchColumn()) {
                    throw new \RuntimeException('Este profissional já está vinculado a outro estabelecimento. O suporte a múltiplas unidades será habilitado com o seletor de estabelecimento.');
                }
            } else {
                if (strlen($password) < 8) {
                    throw new \RuntimeException('Informe uma senha inicial com pelo menos 8 caracteres para a nova conta.');
                }
                $insertUser = $pdo->prepare(
                    'INSERT INTO users (name, email, password_hash, role, phone) VALUES (:name, :email, :password, "employee", :phone)'
                );
                $insertUser->execute([
                    'name' => $name,
                    'email' => $email,
                    'password' => password_hash($password, PASSWORD_DEFAULT),
                    'phone' => $phone !== '' ? $phone : null,
                ]);
                $userId = (int) $pdo->lastInsertId();
            }

            $link = $pdo->prepare(
                'INSERT INTO establishment_users (establishment_id, user_id, role, active) VALUES (:establishment, :user, "employee", 1) '
                . 'ON DUPLICATE KEY UPDATE role = "employee", active = 1'
            );
            $link->execute(['establishment' => $establishmentId, 'user' => $userId]);
            $pdo->commit();
            flash('success', 'Profissional adicionado à equipe.');
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            flash('error', $exception instanceof \RuntimeException ? $exception->getMessage() : 'Não foi possível adicionar o profissional.');
        }

        redirect('/painel/equipe');
    }

    public function toggleStatus(string $id): void
    {
        Auth::requireRole(['owner']);
        Csrf::validate($_POST['_csrf'] ?? null);
        $establishmentId = TenantContext::requireEstablishmentId();
        $userId = (int) $id;
        $pdo = Database::connection();

        $stmt = $pdo->prepare(
            'UPDATE establishment_users SET active = IF(active = 1, 0, 1) '
            . 'WHERE establishment_id = :establishment AND user_id = :user AND role = "employee"'
        );
        $stmt->execute(['establishment' => $establishmentId, 'user' => $userId]);
        flash('success', $stmt->rowCount() ? 'Status do profissional atualizado.' : 'Profissional não encontrado.');
        redirect('/painel/equipe');
    }

    public function schedule(string $id): void
    {
        Auth::requireRole(['owner']);
        $establishmentId = TenantContext::requireEstablishmentId();
        $userId = (int) $id;
        $pdo = Database::connection();
        $professional = $this->professional($pdo, $establishmentId, $userId);
        if (!$professional) {
            http_response_code(404);
            View::render('errors/404', ['title' => 'Profissional não encontrado']);
            return;
        }

        $hours = $pdo->prepare('SELECT * FROM provider_hours WHERE establishment_id = :establishment AND user_id = :user ORDER BY weekday');
        $hours->execute(['establishment' => $establishmentId, 'user' => $userId]);
        $hoursByDay = [];
        foreach ($hours->fetchAll() as $hour) {
            $hoursByDay[(int) $hour['weekday']] = $hour;
        }

        $blocks = $pdo->prepare(
            'SELECT * FROM blocked_periods WHERE establishment_id = :establishment AND employee_user_id = :user '
            . 'AND ends_at >= NOW() ORDER BY starts_at LIMIT 80'
        );
        $blocks->execute(['establishment' => $establishmentId, 'user' => $userId]);

        $business = $pdo->prepare('SELECT * FROM business_hours WHERE establishment_id = :establishment ORDER BY weekday');
        $business->execute(['establishment' => $establishmentId]);
        $businessByDay = [];
        foreach ($business->fetchAll() as $hour) {
            $businessByDay[(int) $hour['weekday']] = $hour;
        }

        View::render('panel/team_schedule', [
            'title' => 'Horários de ' . $professional['name'],
            'professional' => $professional,
            'hoursByDay' => $hoursByDay,
            'businessByDay' => $businessByDay,
            'blocks' => $blocks->fetchAll(),
            'timezone' => $this->timezone($pdo, $establishmentId),
        ]);
    }

    public function storeSchedule(string $id): void
    {
        Auth::requireRole(['owner']);
        Csrf::validate($_POST['_csrf'] ?? null);
        $establishmentId = TenantContext::requireEstablishmentId();
        $userId = (int) $id;
        $pdo = Database::connection();
        if (!$this->professional($pdo, $establishmentId, $userId)) {
            flash('error', 'Profissional não encontrado.');
            redirect('/painel/equipe');
        }

        $pdo->beginTransaction();
        try {
            $delete = $pdo->prepare('DELETE FROM provider_hours WHERE establishment_id = :establishment AND user_id = :user');
            $delete->execute(['establishment' => $establishmentId, 'user' => $userId]);

            $insert = $pdo->prepare(
                'INSERT INTO provider_hours (establishment_id, user_id, weekday, opens_at, closes_at, is_off) '
                . 'VALUES (:establishment, :user, :weekday, :opens, :closes, :off)'
            );
            for ($day = 1; $day <= 7; $day++) {
                $mode = (string) ($_POST['mode'][$day] ?? 'inherit');
                if ($mode === 'inherit') {
                    continue;
                }
                $off = $mode === 'off' ? 1 : 0;
                $opens = trim((string) ($_POST['opens'][$day] ?? ''));
                $closes = trim((string) ($_POST['closes'][$day] ?? ''));
                if (!$off && ($opens === '' || $closes === '' || $opens >= $closes)) {
                    throw new \RuntimeException('Revise os horários personalizados antes de salvar.');
                }
                $insert->execute([
                    'establishment' => $establishmentId,
                    'user' => $userId,
                    'weekday' => $day,
                    'opens' => $off ? null : $opens,
                    'closes' => $off ? null : $closes,
                    'off' => $off,
                ]);
            }
            $pdo->commit();
            flash('success', 'Jornada individual atualizada.');
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            flash('error', $exception instanceof \RuntimeException ? $exception->getMessage() : 'Não foi possível salvar a jornada.');
        }

        redirect('/painel/equipe/' . $userId . '/horarios');
    }

    public function storeBlock(string $id): void
    {
        Auth::requireRole(['owner']);
        Csrf::validate($_POST['_csrf'] ?? null);
        $establishmentId = TenantContext::requireEstablishmentId();
        $userId = (int) $id;
        $pdo = Database::connection();
        if (!$this->professional($pdo, $establishmentId, $userId)) {
            flash('error', 'Profissional não encontrado.');
            redirect('/painel/equipe');
        }

        $date = trim((string) ($_POST['date'] ?? ''));
        $starts = trim((string) ($_POST['starts_at'] ?? ''));
        $ends = trim((string) ($_POST['ends_at'] ?? ''));
        $reason = trim((string) ($_POST['reason'] ?? ''));
        $timezone = new DateTimeZone($this->timezone($pdo, $establishmentId));
        $startAt = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $date . ' ' . $starts, $timezone);
        $endAt = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $date . ' ' . $ends, $timezone);
        if (!$startAt || !$endAt || $endAt <= $startAt) {
            flash('error', 'Informe data e intervalo válidos para o bloqueio.');
            redirect('/painel/equipe/' . $userId . '/horarios');
        }

        $stmt = $pdo->prepare(
            'INSERT INTO blocked_periods (establishment_id, employee_user_id, starts_at, ends_at, reason) '
            . 'VALUES (:establishment, :user, :starts, :ends, :reason)'
        );
        $stmt->execute([
            'establishment' => $establishmentId,
            'user' => $userId,
            'starts' => $startAt->format('Y-m-d H:i:s'),
            'ends' => $endAt->format('Y-m-d H:i:s'),
            'reason' => $reason !== '' ? $reason : null,
        ]);
        flash('success', 'Bloqueio adicionado à agenda do profissional.');
        redirect('/painel/equipe/' . $userId . '/horarios');
    }

    public function deleteBlock(string $id, string $blockId): void
    {
        Auth::requireRole(['owner']);
        Csrf::validate($_POST['_csrf'] ?? null);
        $establishmentId = TenantContext::requireEstablishmentId();
        $userId = (int) $id;
        $stmt = Database::connection()->prepare(
            'DELETE FROM blocked_periods WHERE id = :block AND establishment_id = :establishment AND employee_user_id = :user'
        );
        $stmt->execute(['block' => (int) $blockId, 'establishment' => $establishmentId, 'user' => $userId]);
        flash('success', $stmt->rowCount() ? 'Bloqueio removido.' : 'Bloqueio não encontrado.');
        redirect('/painel/equipe/' . $userId . '/horarios');
    }

    private function professional(\PDO $pdo, int $establishmentId, int $userId): ?array
    {
        $stmt = $pdo->prepare(
            'SELECT u.id,u.name,u.email,CASE WHEN e.owner_user_id=u.id THEN "owner" ELSE "employee" END member_role '
            . 'FROM establishments e JOIN users u ON u.id=:user '
            . 'LEFT JOIN establishment_users eu ON eu.establishment_id=e.id AND eu.user_id=u.id '
            . 'WHERE e.id=:establishment AND (e.owner_user_id=u.id OR (eu.role="employee" AND eu.active=1)) LIMIT 1'
        );
        $stmt->execute(['user' => $userId, 'establishment' => $establishmentId]);
        return $stmt->fetch() ?: null;
    }

    private function timezone(\PDO $pdo, int $establishmentId): string
    {
        $stmt = $pdo->prepare('SELECT timezone FROM establishments WHERE id=:id LIMIT 1');
        $stmt->execute(['id' => $establishmentId]);
        return (string) ($stmt->fetchColumn() ?: env('APP_TIMEZONE', 'America/Sao_Paulo'));
    }
}
