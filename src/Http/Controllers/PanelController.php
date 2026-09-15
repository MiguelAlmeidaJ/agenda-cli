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
        $serviceRows = $services->fetchAll();

        $providers = $this->providersForEstablishment($pdo, $establishmentId);
        $assignmentsStmt = $pdo->prepare(
            'SELECT es.service_id, es.employee_user_id FROM employee_services es '
            . 'JOIN services s ON s.id = es.service_id '
            . 'WHERE s.establishment_id = :establishment'
        );
        $assignmentsStmt->execute(['establishment' => $establishmentId]);
        $assignments = [];
        foreach ($assignmentsStmt->fetchAll() as $assignment) {
            $assignments[(int) $assignment['service_id']][] = (int) $assignment['employee_user_id'];
        }

        View::render('panel/services', [
            'title' => 'Serviços',
            'services' => $serviceRows,
            'providers' => $providers,
            'assignments' => $assignments,
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
        $price = $this->parsePrice((string) ($_POST['price'] ?? '0'));
        $providerIds = array_values(array_unique(array_map('intval', (array) ($_POST['provider_ids'] ?? []))));

        if ($name === '' || $duration < 5 || $price === null || $price < 0) {
            flash('error', 'Revise nome, duração e valor do serviço.');
            redirect('/painel/servicos');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $providers = $this->providersForEstablishment($pdo, $establishmentId);
            $providerIds = $this->validProviderIdsOrOwner($providers, $providerIds);

            $insert = $pdo->prepare(
                'INSERT INTO services (establishment_id, name, description, duration_minutes, price) '
                . 'VALUES (:establishment, :name, :description, :duration, :price)'
            );
            $insert->execute([
                'establishment' => $establishmentId,
                'name' => $name,
                'description' => $description !== '' ? $description : null,
                'duration' => $duration,
                'price' => number_format($price, 2, '.', ''),
            ]);

            $this->syncServiceProviders($pdo, (int) $pdo->lastInsertId(), $providerIds);
            $pdo->commit();
            flash('success', 'Serviço cadastrado e profissionais vinculados.');
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            flash('error', 'Não foi possível cadastrar o serviço.');
        }

        redirect('/painel/servicos');
    }

    public function updateService(string $id): void
    {
        Auth::requireRole(['owner']);
        Csrf::validate($_POST['_csrf'] ?? null);
        $establishmentId = TenantContext::requireEstablishmentId();
        $serviceId = (int) $id;

        $name = trim((string) ($_POST['name'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $duration = (int) ($_POST['duration_minutes'] ?? 0);
        $price = $this->parsePrice((string) ($_POST['price'] ?? '0'));
        $active = isset($_POST['active']) ? 1 : 0;
        $providerIds = array_values(array_unique(array_map('intval', (array) ($_POST['provider_ids'] ?? []))));

        if ($serviceId <= 0 || $name === '' || $duration < 5 || $price === null || $price < 0) {
            flash('error', 'Revise os dados do serviço.');
            redirect('/painel/servicos');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $service = $pdo->prepare('SELECT id FROM services WHERE id = :service AND establishment_id = :establishment LIMIT 1 FOR UPDATE');
            $service->execute(['service' => $serviceId, 'establishment' => $establishmentId]);
            if (!$service->fetchColumn()) {
                throw new \RuntimeException('Serviço não encontrado.');
            }

            $providers = $this->providersForEstablishment($pdo, $establishmentId);
            $providerIds = $this->validProviderIdsOrOwner($providers, $providerIds);

            $update = $pdo->prepare(
                'UPDATE services SET name = :name, description = :description, duration_minutes = :duration, '
                . 'price = :price, active = :active WHERE id = :service AND establishment_id = :establishment'
            );
            $update->execute([
                'name' => $name,
                'description' => $description !== '' ? $description : null,
                'duration' => $duration,
                'price' => number_format($price, 2, '.', ''),
                'active' => $active,
                'service' => $serviceId,
                'establishment' => $establishmentId,
            ]);

            $this->syncServiceProviders($pdo, $serviceId, $providerIds);
            $pdo->commit();
            flash('success', 'Serviço atualizado.');
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            flash('error', $exception instanceof \RuntimeException ? $exception->getMessage() : 'Não foi possível atualizar o serviço.');
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

    private function providersForEstablishment(\PDO $pdo, int $establishmentId): array
    {
        $ownerStmt = $pdo->prepare(
            'SELECT u.id, u.name, "owner" AS provider_role FROM establishments e '
            . 'JOIN users u ON u.id = e.owner_user_id '
            . 'WHERE e.id = :establishment AND u.status = "active" LIMIT 1'
        );
        $ownerStmt->execute(['establishment' => $establishmentId]);
        $owner = $ownerStmt->fetch();

        $employeesStmt = $pdo->prepare(
            'SELECT u.id, u.name, "employee" AS provider_role FROM establishment_users eu '
            . 'JOIN users u ON u.id = eu.user_id '
            . 'WHERE eu.establishment_id = :establishment AND eu.role = "employee" '
            . 'AND eu.active = 1 AND u.status = "active" ORDER BY u.name'
        );
        $employeesStmt->execute(['establishment' => $establishmentId]);

        return array_values(array_filter(array_merge($owner ? [$owner] : [], $employeesStmt->fetchAll())));
    }

    private function validProviderIdsOrOwner(array $providers, array $requestedIds): array
    {
        $allowedIds = array_map('intval', array_column($providers, 'id'));
        $selected = array_values(array_intersect($requestedIds, $allowedIds));

        if ($selected !== []) {
            return $selected;
        }

        foreach ($providers as $provider) {
            if (($provider['provider_role'] ?? null) === 'owner') {
                return [(int) $provider['id']];
            }
        }

        throw new \RuntimeException('O serviço precisa ter ao menos um profissional vinculado.');
    }

    private function syncServiceProviders(\PDO $pdo, int $serviceId, array $providerIds): void
    {
        $delete = $pdo->prepare('DELETE FROM employee_services WHERE service_id = :service');
        $delete->execute(['service' => $serviceId]);

        $insert = $pdo->prepare('INSERT INTO employee_services (employee_user_id, service_id) VALUES (:provider, :service)');
        foreach ($providerIds as $providerId) {
            $insert->execute(['provider' => (int) $providerId, 'service' => $serviceId]);
        }
    }

    private function parsePrice(string $raw): ?float
    {
        $value = preg_replace('/\s+/', '', trim($raw)) ?? '';
        if ($value === '') {
            return null;
        }

        if (str_contains($value, ',') && str_contains($value, '.')) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        } else {
            $value = str_replace(',', '.', $value);
        }

        return is_numeric($value) ? (float) $value : null;
    }
}
