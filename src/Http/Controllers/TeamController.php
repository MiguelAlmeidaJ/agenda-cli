<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\TenantContext;
use App\Core\View;
use App\Services\EstablishmentClock;
use App\Services\ProviderAbsenceService;
use App\Services\ScheduleRangeService;
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
                    throw new \RuntimeException('Defina uma senha inicial com pelo menos 8 caracteres.');
                }
                $insertUser = $pdo->prepare(
                    'INSERT INTO users (name, email, password_hash, role, phone) '
                    . 'VALUES (:name, :email, :password, "employee", :phone)'
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
                'INSERT INTO establishment_users (establishment_id, user_id, role, active) '
                . 'VALUES (:establishment, :user, "employee", 1) '
                . 'ON DUPLICATE KEY UPDATE role = "employee", active = 1'
            );
            $link->execute(['establishment' => $establishmentId, 'user' => $userId]);

            $pdo->commit();
            flash('success', $existing ? 'Profissional existente vinculado à equipe.' : 'Profissional adicionado à equipe.');
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
        $changed = $stmt->rowCount() > 0;

        flash($changed ? 'success' : 'error', $changed ? 'Status do profissional atualizado.' : 'Profissional não encontrado.');
        redirect('/painel/equipe');
    }

    public function schedule(string $id): void
    {
        Auth::requireRole(['owner']);
        $establishmentId = TenantContext::requireEstablishmentId();
        $providerId = (int) $id;
        $pdo = Database::connection();

        $provider = $this->provider($pdo, $establishmentId, $providerId);
        if (!$provider) {
            http_response_code(404);
            View::render('errors/404', ['title' => 'Profissional não encontrado']);
            return;
        }

        $hoursStmt = $pdo->prepare(
            'SELECT * FROM provider_hours WHERE establishment_id = :establishment AND user_id = :provider ORDER BY weekday'
        );
        $hoursStmt->execute(['establishment' => $establishmentId, 'provider' => $providerId]);
        $hours = [];
        foreach ($hoursStmt->fetchAll() as $row) {
            $hours[(int) $row['weekday']] = $row;
        }

        $providerHourRanges = [];
        try {
            $rangeStmt = $pdo->prepare(
                'SELECT weekday,opens_at,closes_at FROM provider_hour_ranges '
                . 'WHERE establishment_id=:establishment AND user_id=:provider ORDER BY weekday,sort_order,id'
            );
            $rangeStmt->execute(['establishment' => $establishmentId, 'provider' => $providerId]);
            foreach ($rangeStmt->fetchAll() as $range) {
                $weekday = (int) $range['weekday'];
                $providerHourRanges[$weekday][] = [
                    'opens_at' => substr((string) $range['opens_at'], 0, 5),
                    'closes_at' => substr((string) $range['closes_at'], 0, 5),
                ];
            }
        } catch (\PDOException) {
            // Compatibilidade enquanto a migration ainda não foi aplicada.
        }
        foreach ($hours as $weekday => $row) {
            if (!isset($providerHourRanges[$weekday]) && (int) $row['is_off'] !== 1 && $row['opens_at'] && $row['closes_at']) {
                $providerHourRanges[$weekday] = [[
                    'opens_at' => substr((string) $row['opens_at'], 0, 5),
                    'closes_at' => substr((string) $row['closes_at'], 0, 5),
                ]];
            }
        }

        $businessStmt = $pdo->prepare('SELECT * FROM business_hours WHERE establishment_id = :establishment ORDER BY weekday');
        $businessStmt->execute(['establishment' => $establishmentId]);
        $businessHours = [];
        foreach ($businessStmt->fetchAll() as $row) {
            $businessHours[(int) $row['weekday']] = $row;
        }

        $businessHourRanges = [];
        try {
            $rangeStmt = $pdo->prepare(
                'SELECT weekday,opens_at,closes_at FROM business_hour_ranges '
                . 'WHERE establishment_id=:establishment ORDER BY weekday,sort_order,id'
            );
            $rangeStmt->execute(['establishment' => $establishmentId]);
            foreach ($rangeStmt->fetchAll() as $range) {
                $weekday = (int) $range['weekday'];
                $businessHourRanges[$weekday][] = [
                    'opens_at' => substr((string) $range['opens_at'], 0, 5),
                    'closes_at' => substr((string) $range['closes_at'], 0, 5),
                ];
            }
        } catch (\PDOException) {
            // Compatibilidade enquanto a migration ainda não foi aplicada.
        }
        foreach ($businessHours as $weekday => $row) {
            if (!isset($businessHourRanges[$weekday]) && (int) $row['is_closed'] !== 1 && $row['opens_at'] && $row['closes_at']) {
                $businessHourRanges[$weekday] = [[
                    'opens_at' => substr((string) $row['opens_at'], 0, 5),
                    'closes_at' => substr((string) $row['closes_at'], 0, 5),
                ]];
            }
        }

        $clock = new EstablishmentClock();
        $now = $clock->now($pdo, $establishmentId);
        $blocks = $pdo->prepare(
            'SELECT id, starts_at, ends_at, reason FROM blocked_periods '
            . 'WHERE establishment_id = :establishment AND employee_user_id = :provider AND ends_at >= :now '
            . 'ORDER BY starts_at ASC LIMIT 30'
        );
        $blocks->execute([
            'establishment' => $establishmentId,
            'provider' => $providerId,
            'now' => $clock->sql($now),
        ]);

        $absences = [];
        try {
            $absenceStmt = $pdo->prepare(
                'SELECT * FROM provider_absences '
                . 'WHERE establishment_id=:establishment AND user_id=:provider '
                . 'AND (ends_on IS NULL OR ends_on>=:today) '
                . 'ORDER BY CASE kind WHEN "date_range" THEN 0 ELSE 1 END,starts_on,weekday,id LIMIT 80'
            );
            $absenceStmt->execute([
                'establishment' => $establishmentId,
                'provider' => $providerId,
                'today' => $now->format('Y-m-d'),
            ]);
            $absences = $absenceStmt->fetchAll();
        } catch (\PDOException) {
            // Compatibilidade enquanto a migration de ausências ainda não foi aplicada.
        }

        View::render('panel/team_schedule', [
            'title' => 'Horários de ' . $provider['name'],
            'provider' => $provider,
            'hours' => $hours,
            'providerHourRanges' => $providerHourRanges,
            'businessHours' => $businessHours,
            'businessHourRanges' => $businessHourRanges,
            'blocks' => $blocks->fetchAll(),
            'absences' => $absences,
            'today' => $now->format('Y-m-d'),
        ]);
    }

    public function storeSchedule(string $id): void
    {
        Auth::requireRole(['owner']);
        Csrf::validate($_POST['_csrf'] ?? null);
        $establishmentId = TenantContext::requireEstablishmentId();
        $providerId = (int) $id;
        $pdo = Database::connection();

        if (!$this->provider($pdo, $establishmentId, $providerId)) {
            flash('error', 'Profissional não encontrado.');
            redirect('/painel/equipe');
        }

        $rangeService = new ScheduleRangeService();
        $upsert = $pdo->prepare(
            'INSERT INTO provider_hours (establishment_id, user_id, weekday, opens_at, closes_at, is_off) '
            . 'VALUES (:establishment, :provider, :weekday, :opens, :closes, :off) '
            . 'ON DUPLICATE KEY UPDATE opens_at = VALUES(opens_at), closes_at = VALUES(closes_at), is_off = VALUES(is_off)'
        );
        $delete = $pdo->prepare(
            'DELETE FROM provider_hours WHERE establishment_id = :establishment AND user_id = :provider AND weekday = :weekday'
        );
        $deleteRanges = $pdo->prepare(
            'DELETE FROM provider_hour_ranges WHERE establishment_id=:establishment AND user_id=:provider AND weekday=:weekday'
        );
        $insertRange = $pdo->prepare(
            'INSERT INTO provider_hour_ranges (establishment_id,user_id,weekday,opens_at,closes_at,sort_order) '
            . 'VALUES (:establishment,:provider,:weekday,:opens,:closes,:sort_order)'
        );

        $pdo->beginTransaction();
        try {
            for ($weekday = 1; $weekday <= 7; $weekday++) {
                $mode = (string) ($_POST['mode'][$weekday] ?? 'inherit');

                $deleteRanges->execute([
                    'establishment' => $establishmentId,
                    'provider' => $providerId,
                    'weekday' => $weekday,
                ]);

                if ($mode === 'inherit') {
                    $delete->execute([
                        'establishment' => $establishmentId,
                        'provider' => $providerId,
                        'weekday' => $weekday,
                    ]);
                    continue;
                }

                if ($mode === 'off') {
                    $upsert->execute([
                        'establishment' => $establishmentId,
                        'provider' => $providerId,
                        'weekday' => $weekday,
                        'opens' => null,
                        'closes' => null,
                        'off' => 1,
                    ]);
                    continue;
                }

                if ($mode !== 'custom') {
                    throw new \RuntimeException('Regra de jornada inválida.');
                }

                $ranges = $rangeService->normalize((array) ($_POST['ranges'][$weekday] ?? []));
                if ($ranges === []) {
                    throw new \RuntimeException('Adicione ao menos uma faixa nos dias com horário próprio.');
                }

                $upsert->execute([
                    'establishment' => $establishmentId,
                    'provider' => $providerId,
                    'weekday' => $weekday,
                    'opens' => $ranges[0]['opens_at'],
                    'closes' => $ranges[count($ranges) - 1]['closes_at'],
                    'off' => 0,
                ]);

                foreach ($ranges as $index => $range) {
                    $insertRange->execute([
                        'establishment' => $establishmentId,
                        'provider' => $providerId,
                        'weekday' => $weekday,
                        'opens' => $range['opens_at'],
                        'closes' => $range['closes_at'],
                        'sort_order' => $index,
                    ]);
                }
            }

            $pdo->commit();
            flash('success', 'Horários individuais atualizados.');
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            flash('error', $exception instanceof \RuntimeException ? $exception->getMessage() : 'Não foi possível atualizar a jornada.');
        }

        redirect('/painel/equipe/' . $providerId . '/horarios');
    }

    public function storeAbsence(string $id): void
    {
        Auth::requireRole(['owner']);
        Csrf::validate($_POST['_csrf'] ?? null);
        $establishmentId = TenantContext::requireEstablishmentId();
        $providerId = (int) $id;
        $pdo = Database::connection();

        if (!$this->provider($pdo, $establishmentId, $providerId)) {
            flash('error', 'Profissional não encontrado.');
            redirect('/painel/equipe');
        }

        $clock = new EstablishmentClock();
        $today = $clock->now($pdo, $establishmentId)->format('Y-m-d');
        $service = new ProviderAbsenceService();
        $type = (string) ($_POST['absence_type'] ?? '');

        try {
            if ($type === 'date_range') {
                $rule = $service->normalizeDateRange(
                    (string) ($_POST['starts_on'] ?? ''),
                    (string) ($_POST['ends_on'] ?? ''),
                    (string) ($_POST['reason'] ?? '')
                );
                if ($rule['ends_on'] < $today) {
                    throw new \RuntimeException('O período informado já terminou.');
                }
            } elseif ($type === 'weekly') {
                $rule = $service->normalizeWeekly(
                    (int) ($_POST['weekday'] ?? 0),
                    (string) ($_POST['starts_on'] ?? $today),
                    trim((string) ($_POST['ends_on'] ?? '')) !== '' ? (string) $_POST['ends_on'] : null,
                    (string) ($_POST['starts_at'] ?? ''),
                    (string) ($_POST['ends_at'] ?? ''),
                    (string) ($_POST['reason'] ?? '')
                );
                if ($rule['ends_on'] !== null && $rule['ends_on'] < $today) {
                    throw new \RuntimeException('A recorrência informada já terminou.');
                }
            } else {
                throw new \RuntimeException('Tipo de ausência inválido.');
            }

            if ($this->absenceHasAppointments($pdo, $establishmentId, $providerId, $rule)) {
                throw new \RuntimeException('Existem agendamentos ativos dentro desse período. Reagende ou cancele esses atendimentos antes de criar a ausência.');
            }

            $insert = $pdo->prepare(
                'INSERT INTO provider_absences '
                . '(establishment_id,user_id,kind,starts_on,ends_on,weekday,all_day,starts_at,ends_at,reason) '
                . 'VALUES (:establishment,:provider,:kind,:starts_on,:ends_on,:weekday,:all_day,:starts_at,:ends_at,:reason)'
            );
            $insert->execute([
                'establishment' => $establishmentId,
                'provider' => $providerId,
                'kind' => $rule['kind'],
                'starts_on' => $rule['starts_on'],
                'ends_on' => $rule['ends_on'],
                'weekday' => $rule['weekday'],
                'all_day' => $rule['all_day'],
                'starts_at' => $rule['starts_at'],
                'ends_at' => $rule['ends_at'],
                'reason' => $rule['reason'],
            ]);

            flash('success', $type === 'date_range' ? 'Período de férias/ausência salvo.' : 'Ausência recorrente salva.');
        } catch (\Throwable $exception) {
            flash('error', $exception instanceof \RuntimeException ? $exception->getMessage() : 'Não foi possível salvar a ausência.');
        }

        redirect('/painel/equipe/' . $providerId . '/horarios');
    }

    public function deleteAbsence(string $id, string $absenceId): void
    {
        Auth::requireRole(['owner']);
        Csrf::validate($_POST['_csrf'] ?? null);
        $establishmentId = TenantContext::requireEstablishmentId();
        $providerId = (int) $id;

        $stmt = Database::connection()->prepare(
            'DELETE FROM provider_absences '
            . 'WHERE id=:absence AND establishment_id=:establishment AND user_id=:provider'
        );
        $stmt->execute([
            'absence' => (int) $absenceId,
            'establishment' => $establishmentId,
            'provider' => $providerId,
        ]);

        flash($stmt->rowCount() ? 'success' : 'error', $stmt->rowCount() ? 'Ausência removida.' : 'Ausência não encontrada.');
        redirect('/painel/equipe/' . $providerId . '/horarios');
    }

    public function storeBlock(string $id): void
    {
        Auth::requireRole(['owner']);
        Csrf::validate($_POST['_csrf'] ?? null);
        $establishmentId = TenantContext::requireEstablishmentId();
        $providerId = (int) $id;
        $pdo = Database::connection();

        if (!$this->provider($pdo, $establishmentId, $providerId)) {
            flash('error', 'Profissional não encontrado.');
            redirect('/painel/equipe');
        }

        $date = trim((string) ($_POST['date'] ?? ''));
        $starts = trim((string) ($_POST['starts_at'] ?? ''));
        $ends = trim((string) ($_POST['ends_at'] ?? ''));
        $reason = trim((string) ($_POST['reason'] ?? ''));

        $timezoneStmt = $pdo->prepare('SELECT timezone FROM establishments WHERE id = :establishment LIMIT 1');
        $timezoneStmt->execute(['establishment' => $establishmentId]);
        $timezone = new DateTimeZone((string) ($timezoneStmt->fetchColumn() ?: 'America/Sao_Paulo'));

        try {
            $startAt = new DateTimeImmutable($date . ' ' . $starts, $timezone);
            $endAt = new DateTimeImmutable($date . ' ' . $ends, $timezone);
        } catch (\Throwable) {
            flash('error', 'Informe uma data e horários válidos.');
            redirect('/painel/equipe/' . $providerId . '/horarios');
        }

        if ($date === '' || $starts === '' || $ends === '' || $endAt <= $startAt) {
            flash('error', 'O fim do bloqueio deve ser depois do início.');
            redirect('/painel/equipe/' . $providerId . '/horarios');
        }

        $insert = $pdo->prepare(
            'INSERT INTO blocked_periods (establishment_id, employee_user_id, starts_at, ends_at, reason) '
            . 'VALUES (:establishment, :provider, :starts, :ends, :reason)'
        );
        $insert->execute([
            'establishment' => $establishmentId,
            'provider' => $providerId,
            'starts' => $startAt->format('Y-m-d H:i:s'),
            'ends' => $endAt->format('Y-m-d H:i:s'),
            'reason' => $reason !== '' ? $reason : null,
        ]);

        flash('success', 'Bloqueio adicionado à agenda do profissional.');
        redirect('/painel/equipe/' . $providerId . '/horarios');
    }

    public function deleteBlock(string $id, string $blockId): void
    {
        Auth::requireRole(['owner']);
        Csrf::validate($_POST['_csrf'] ?? null);
        $establishmentId = TenantContext::requireEstablishmentId();
        $providerId = (int) $id;
        $block = (int) $blockId;

        $stmt = Database::connection()->prepare(
            'DELETE FROM blocked_periods WHERE id = :block AND establishment_id = :establishment AND employee_user_id = :provider'
        );
        $stmt->execute(['block' => $block, 'establishment' => $establishmentId, 'provider' => $providerId]);

        flash('success', 'Bloqueio removido.');
        redirect('/painel/equipe/' . $providerId . '/horarios');
    }

    private function absenceHasAppointments(\PDO $pdo, int $establishmentId, int $providerId, array $rule): bool
    {
        if (($rule['kind'] ?? null) === 'date_range') {
            $start = (string) $rule['starts_on'] . ' 00:00:00';
            $endExclusive = (new DateTimeImmutable((string) $rule['ends_on'] . ' 00:00:00'))
                ->modify('+1 day')
                ->format('Y-m-d H:i:s');

            $stmt = $pdo->prepare(
                'SELECT 1 FROM appointments WHERE establishment_id=:establishment '
                . 'AND employee_user_id=:provider AND status IN ("pending","confirmed") '
                . 'AND starts_at<:end_exclusive AND ends_at>:start LIMIT 1'
            );
            $stmt->execute([
                'establishment' => $establishmentId,
                'provider' => $providerId,
                'end_exclusive' => $endExclusive,
                'start' => $start,
            ]);
            return (bool) $stmt->fetchColumn();
        }

        $sql = 'SELECT 1 FROM appointments WHERE establishment_id=:establishment '
            . 'AND employee_user_id=:provider AND status IN ("pending","confirmed") '
            . 'AND starts_at>=:starts_on AND (WEEKDAY(starts_at)+1)=:weekday '
            . 'AND TIME(starts_at)<:ends_at AND TIME(ends_at)>:starts_at';
        $params = [
            'establishment' => $establishmentId,
            'provider' => $providerId,
            'starts_on' => (string) $rule['starts_on'] . ' 00:00:00',
            'weekday' => (int) $rule['weekday'],
            'ends_at' => (string) $rule['ends_at'],
            'starts_at' => (string) $rule['starts_at'],
        ];

        if (!empty($rule['ends_on'])) {
            $sql .= ' AND starts_at<:ends_on';
            $params['ends_on'] = (new DateTimeImmutable((string) $rule['ends_on'] . ' 00:00:00'))
                ->modify('+1 day')
                ->format('Y-m-d H:i:s');
        }

        $sql .= ' LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (bool) $stmt->fetchColumn();
    }

    private function provider(\PDO $pdo, int $establishmentId, int $providerId): ?array
    {
        $stmt = $pdo->prepare(
            'SELECT u.id, u.name, u.email, CASE WHEN u.id = e.owner_user_id THEN "owner" ELSE "employee" END AS member_role '
            . 'FROM establishments e JOIN users u ON u.id = :provider '
            . 'LEFT JOIN establishment_users eu ON eu.establishment_id = e.id AND eu.user_id = u.id '
            . 'WHERE e.id = :establishment AND (u.id = e.owner_user_id OR eu.role = "employee") LIMIT 1'
        );
        $stmt->execute(['provider' => $providerId, 'establishment' => $establishmentId]);
        $provider = $stmt->fetch();
        return $provider ?: null;
    }
}
