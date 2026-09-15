<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\TenantContext;
use App\Core\View;

final class PanelController
{
    public function index(): void
    {
        Auth::requireLogin();
        $pdo = Database::connection();
        $role = Auth::role();
        $metrics = [];
        $dashboard = [
            'establishment' => null,
            'nextAppointments' => [],
            'todayRevenue' => 0.0,
            'pendingToday' => 0,
        ];

        if ($role === 'admin') {
            $metrics = [
                'Estabelecimentos' => (int) $pdo->query('SELECT COUNT(*) FROM establishments WHERE active = 1')->fetchColumn(),
                'Usuários' => (int) $pdo->query('SELECT COUNT(*) FROM users WHERE status = "active"')->fetchColumn(),
                'Agendamentos' => (int) $pdo->query('SELECT COUNT(*) FROM appointments')->fetchColumn(),
            ];
        } elseif ($role === 'client') {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM appointments WHERE client_user_id = :user AND starts_at >= NOW() AND status IN ("pending", "confirmed")');
            $stmt->execute(['user' => Auth::id()]);
            $metrics = ['Próximos agendamentos' => (int) $stmt->fetchColumn()];
        } else {
            $establishmentId = TenantContext::requireEstablishmentId();

            if ($role === 'employee') {
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM appointments WHERE establishment_id = :establishment AND employee_user_id = :user AND DATE(starts_at) = CURDATE() AND status IN ("pending", "confirmed")');
                $stmt->execute(['establishment' => $establishmentId, 'user' => Auth::id()]);
                $metrics = ['Atendimentos hoje' => (int) $stmt->fetchColumn()];
            } else {
                $establishment = $pdo->prepare('SELECT id, name, slug FROM establishments WHERE id = :establishment LIMIT 1');
                $establishment->execute(['establishment' => $establishmentId]);

                $services = $pdo->prepare('SELECT COUNT(*) FROM services WHERE establishment_id = :establishment AND active = 1');
                $services->execute(['establishment' => $establishmentId]);

                $employees = $pdo->prepare('SELECT COUNT(*) FROM establishment_users WHERE establishment_id = :establishment AND role = "employee" AND active = 1');
                $employees->execute(['establishment' => $establishmentId]);

                $today = $pdo->prepare(
                    'SELECT COUNT(*) AS total, COALESCE(SUM(price), 0) AS revenue, '
                    . 'COALESCE(SUM(status = "pending"), 0) AS pending '
                    . 'FROM appointments WHERE establishment_id = :establishment '
                    . 'AND DATE(starts_at) = CURDATE() AND status IN ("pending", "confirmed")'
                );
                $today->execute(['establishment' => $establishmentId]);
                $todaySummary = $today->fetch() ?: ['total' => 0, 'revenue' => 0, 'pending' => 0];

                $week = $pdo->prepare(
                    'SELECT COUNT(*) FROM appointments WHERE establishment_id = :establishment '
                    . 'AND starts_at >= NOW() AND starts_at < DATE_ADD(NOW(), INTERVAL 7 DAY) '
                    . 'AND status IN ("pending", "confirmed")'
                );
                $week->execute(['establishment' => $establishmentId]);

                $nextAppointments = $pdo->prepare(
                    'SELECT a.id, a.starts_at, a.status, a.price, s.name AS service_name, '
                    . 'employee.name AS employee_name, client.name AS client_name '
                    . 'FROM appointments a '
                    . 'JOIN services s ON s.id = a.service_id '
                    . 'JOIN users employee ON employee.id = a.employee_user_id '
                    . 'JOIN users client ON client.id = a.client_user_id '
                    . 'WHERE a.establishment_id = :establishment AND a.starts_at >= NOW() '
                    . 'AND a.status IN ("pending", "confirmed") '
                    . 'ORDER BY a.starts_at ASC LIMIT 6'
                );
                $nextAppointments->execute(['establishment' => $establishmentId]);

                $metrics = [
                    'Agendamentos hoje' => (int) $todaySummary['total'],
                    'Próximos 7 dias' => (int) $week->fetchColumn(),
                    'Serviços ativos' => (int) $services->fetchColumn(),
                    'Funcionários' => (int) $employees->fetchColumn(),
                ];

                $dashboard = [
                    'establishment' => $establishment->fetch() ?: null,
                    'nextAppointments' => $nextAppointments->fetchAll(),
                    'todayRevenue' => (float) $todaySummary['revenue'],
                    'pendingToday' => (int) $todaySummary['pending'],
                ];
            }
        }

        View::render('panel/index', [
            'title' => 'Painel',
            'metrics' => $metrics,
            'dashboard' => $dashboard,
            'role' => $role,
        ]);
    }

    public function services(): void
    {
        Auth::requireRole(['owner']);
        $establishmentId = TenantContext::requireEstablishmentId();
        $pdo = Database::connection();

        $services = $pdo->prepare('SELECT * FROM services WHERE establishment_id = :establishment ORDER BY active DESC, name');
        $services->execute(['establishment' => $establishmentId]);

        $employees = $pdo->prepare(
            'SELECT u.id, u.name FROM establishment_users eu JOIN users u ON u.id = eu.user_id '
            . 'WHERE eu.establishment_id = :establishment AND eu.role = "employee" AND eu.active = 1 ORDER BY u.name'
        );
        $employees->execute(['establishment' => $establishmentId]);

        View::render('panel/services', [
            'title' => 'Serviços',
            'services' => $services->fetchAll(),
            'employees' => $employees->fetchAll(),
        ]);
    }

    public function storeService(): void
    {
        Auth::requireRole(['owner']);
        Csrf::validate($_POST['_csrf'] ?? null);
        $establishmentId = TenantContext::requireEstablishmentId();
        $name = trim((string) ($_POST['name'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $duration = (int) ($_POST['duration_minutes'] ?? 0);
        $price = str_replace(',', '.', trim((string) ($_POST['price'] ?? '0')));
        $employeeIds = array_map('intval', (array) ($_POST['employee_ids'] ?? []));

        if ($name === '' || $duration < 5 || !is_numeric($price) || (float) $price < 0) {
            flash('error', 'Revise nome, duração e valor do serviço.');
            redirect('/painel/servicos');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $insert = $pdo->prepare(
                'INSERT INTO services (establishment_id, name, description, duration_minutes, price) '
                . 'VALUES (:establishment, :name, :description, :duration, :price)'
            );
            $insert->execute([
                'establishment' => $establishmentId,
                'name' => $name,
                'description' => $description !== '' ? $description : null,
                'duration' => $duration,
                'price' => number_format((float) $price, 2, '.', ''),
            ]);
            $serviceId = (int) $pdo->lastInsertId();

            if ($employeeIds !== []) {
                $verify = $pdo->prepare(
                    'SELECT user_id FROM establishment_users WHERE establishment_id = :establishment AND role = "employee" AND active = 1'
                );
                $verify->execute(['establishment' => $establishmentId]);
                $allowedIds = array_map('intval', array_column($verify->fetchAll(), 'user_id'));
                $link = $pdo->prepare('INSERT IGNORE INTO employee_services (employee_user_id, service_id) VALUES (:employee, :service)');
                foreach (array_intersect($employeeIds, $allowedIds) as $employeeId) {
                    $link->execute(['employee' => $employeeId, 'service' => $serviceId]);
                }
            }

            $pdo->commit();
            flash('success', 'Serviço cadastrado.');
        } catch (\Throwable $exception) {
            $pdo->rollBack();
            flash('error', 'Não foi possível cadastrar o serviço.');
        }
        redirect('/painel/servicos');
    }

    public function hours(): void
    {
        Auth::requireRole(['owner']);
        $establishmentId = TenantContext::requireEstablishmentId();
        $stmt = Database::connection()->prepare('SELECT * FROM business_hours WHERE establishment_id = :establishment ORDER BY weekday');
        $stmt->execute(['establishment' => $establishmentId]);
        $hours = [];
        foreach ($stmt->fetchAll() as $row) {
            $hours[(int) $row['weekday']] = $row;
        }

        View::render('panel/hours', ['title' => 'Horários de funcionamento', 'hours' => $hours]);
    }

    public function storeHours(): void
    {
        Auth::requireRole(['owner']);
        Csrf::validate($_POST['_csrf'] ?? null);
        $establishmentId = TenantContext::requireEstablishmentId();
        $pdo = Database::connection();
        $upsert = $pdo->prepare(
            'INSERT INTO business_hours (establishment_id, weekday, opens_at, closes_at, is_closed) '
            . 'VALUES (:establishment, :weekday, :opens, :closes, :closed) '
            . 'ON DUPLICATE KEY UPDATE opens_at = VALUES(opens_at), closes_at = VALUES(closes_at), is_closed = VALUES(is_closed)'
        );

        for ($weekday = 1; $weekday <= 7; $weekday++) {
            $closed = isset($_POST['closed'][$weekday]);
            $opens = trim((string) ($_POST['opens'][$weekday] ?? ''));
            $closes = trim((string) ($_POST['closes'][$weekday] ?? ''));
            if (!$closed && ($opens === '' || $closes === '' || $opens >= $closes)) {
                flash('error', 'Confira os horários de abertura e fechamento.');
                redirect('/painel/horarios');
            }
            $upsert->execute([
                'establishment' => $establishmentId,
                'weekday' => $weekday,
                'opens' => $closed ? null : $opens,
                'closes' => $closed ? null : $closes,
                'closed' => $closed ? 1 : 0,
            ]);
        }

        flash('success', 'Horários atualizados.');
        redirect('/painel/horarios');
    }

    public function appointments(): void
    {
        Auth::requireLogin();
        $pdo = Database::connection();
        $role = Auth::role();

        $sql = 'SELECT a.*, e.name AS establishment_name, s.name AS service_name, '
            . 'employee.name AS employee_name, client.name AS client_name '
            . 'FROM appointments a '
            . 'JOIN establishments e ON e.id = a.establishment_id '
            . 'JOIN services s ON s.id = a.service_id '
            . 'JOIN users employee ON employee.id = a.employee_user_id '
            . 'JOIN users client ON client.id = a.client_user_id ';
        $params = [];

        if ($role === 'client') {
            $sql .= 'WHERE a.client_user_id = :user ';
            $params['user'] = Auth::id();
        } elseif ($role === 'employee') {
            $sql .= 'WHERE a.establishment_id = :establishment AND a.employee_user_id = :user ';
            $params['establishment'] = TenantContext::requireEstablishmentId();
            $params['user'] = Auth::id();
        } elseif ($role === 'owner') {
            $sql .= 'WHERE a.establishment_id = :establishment ';
            $params['establishment'] = TenantContext::requireEstablishmentId();
        }

        $sql .= 'ORDER BY a.starts_at DESC LIMIT 200';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        View::render('panel/appointments', [
            'title' => 'Agendamentos',
            'appointments' => $stmt->fetchAll(),
            'role' => $role,
        ]);
    }
}
