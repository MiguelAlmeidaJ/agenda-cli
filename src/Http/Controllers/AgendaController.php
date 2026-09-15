<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\TenantContext;
use App\Core\View;
use DateTimeImmutable;

final class AgendaController
{
    public function index(): void
    {
        Auth::requireRole(['owner', 'employee']);
        $establishmentId = TenantContext::requireEstablishmentId();
        $pdo = Database::connection();

        $date = trim((string) ($_GET['date'] ?? date('Y-m-d')));
        $day = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$day || $day->format('Y-m-d') !== $date) {
            $date = date('Y-m-d');
            $day = new DateTimeImmutable('today');
        }

        if (Auth::role() === 'employee') {
            $providerStmt = $pdo->prepare('SELECT id, name FROM users WHERE id = :user LIMIT 1');
            $providerStmt->execute(['user' => Auth::id()]);
            $providers = array_filter([$providerStmt->fetch()]);
        } else {
            $providerStmt = $pdo->prepare(
                'SELECT u.id, u.name, 0 AS sort_order FROM establishments e JOIN users u ON u.id = e.owner_user_id '
                . 'WHERE e.id = :establishment '
                . 'UNION ALL '
                . 'SELECT u.id, u.name, 1 AS sort_order FROM establishment_users eu JOIN users u ON u.id = eu.user_id '
                . 'WHERE eu.establishment_id = :establishment2 AND eu.role = "employee" AND eu.active = 1 '
                . 'ORDER BY sort_order, name'
            );
            $providerStmt->execute([
                'establishment' => $establishmentId,
                'establishment2' => $establishmentId,
            ]);
            $providers = $providerStmt->fetchAll();
        }

        $appointmentsSql = 'SELECT a.id, a.employee_user_id, a.starts_at, a.ends_at, a.status, a.price, '
            . 's.name AS service_name, client.name AS client_name '
            . 'FROM appointments a JOIN services s ON s.id = a.service_id '
            . 'JOIN users client ON client.id = a.client_user_id '
            . 'WHERE a.establishment_id = :establishment AND DATE(a.starts_at) = :date ';
        $params = ['establishment' => $establishmentId, 'date' => $date];
        if (Auth::role() === 'employee') {
            $appointmentsSql .= 'AND a.employee_user_id = :user ';
            $params['user'] = Auth::id();
        }
        $appointmentsSql .= 'ORDER BY a.starts_at ASC';
        $appointmentsStmt = $pdo->prepare($appointmentsSql);
        $appointmentsStmt->execute($params);

        $appointmentsByProvider = [];
        foreach ($appointmentsStmt->fetchAll() as $appointment) {
            $appointmentsByProvider[(int) $appointment['employee_user_id']][] = $appointment;
        }

        View::render('panel/agenda', [
            'title' => 'Agenda do dia',
            'date' => $date,
            'day' => $day,
            'providers' => array_values($providers),
            'appointmentsByProvider' => $appointmentsByProvider,
        ]);
    }
}
