<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Services\AvailabilityService;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

final class BookingController
{
    public function availability(string $slug): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $pdo = Database::connection();
        $establishment = $pdo->prepare('SELECT id FROM establishments WHERE slug = :slug AND active = 1 LIMIT 1');
        $establishment->execute(['slug' => $slug]);
        $establishmentId = (int) ($establishment->fetchColumn() ?: 0);

        $serviceId = (int) ($_GET['service_id'] ?? 0);
        $employeeId = (int) ($_GET['employee_id'] ?? 0);
        $date = (string) ($_GET['date'] ?? '');

        if ($establishmentId === 0 || $serviceId === 0 || $employeeId === 0 || $date === '') {
            http_response_code(422);
            echo json_encode(['slots' => []], JSON_UNESCAPED_UNICODE);
            return;
        }

        $slots = (new AvailabilityService())->slots($establishmentId, $serviceId, $employeeId, $date);
        echo json_encode(['slots' => $slots], JSON_UNESCAPED_UNICODE);
    }

    public function store(string $slug): void
    {
        Auth::requireRole(['client']);
        Csrf::validate($_POST['_csrf'] ?? null);

        $serviceId = (int) ($_POST['service_id'] ?? 0);
        $employeeId = (int) ($_POST['employee_id'] ?? 0);
        $date = trim((string) ($_POST['date'] ?? ''));
        $time = trim((string) ($_POST['time'] ?? ''));
        $notes = trim((string) ($_POST['notes'] ?? ''));

        $pdo = Database::connection();
        $establishmentStmt = $pdo->prepare('SELECT id, owner_user_id, timezone FROM establishments WHERE slug = :slug AND active = 1 LIMIT 1');
        $establishmentStmt->execute(['slug' => $slug]);
        $establishment = $establishmentStmt->fetch();

        if (!$establishment || !$serviceId || !$employeeId || !$date || !$time) {
            flash('error', 'Selecione serviço, profissional, data e horário.');
            redirect('/estabelecimentos/' . $slug);
        }

        $pdo->beginTransaction();
        try {
            // O lock é feito no usuário prestador, então reservas concorrentes para serviços
            // diferentes do mesmo profissional também são serializadas.
            $lock = $pdo->prepare('SELECT id FROM users WHERE id = :provider AND status = "active" FOR UPDATE');
            $lock->execute(['provider' => $employeeId]);
            if (!$lock->fetchColumn()) {
                throw new \RuntimeException('Profissional indisponível.');
            }

            $provider = $pdo->prepare(
                'SELECT 1 FROM employee_services es '
                . 'JOIN services s ON s.id = es.service_id '
                . 'JOIN establishments e ON e.id = s.establishment_id '
                . 'LEFT JOIN establishment_users eu ON eu.establishment_id = e.id AND eu.user_id = es.employee_user_id '
                . 'WHERE e.id = :establishment AND s.id = :service AND es.employee_user_id = :provider '
                . 'AND (es.employee_user_id = e.owner_user_id OR (eu.role = "employee" AND eu.active = 1)) LIMIT 1'
            );
            $provider->execute([
                'establishment' => $establishment['id'],
                'service' => $serviceId,
                'provider' => $employeeId,
            ]);
            if (!$provider->fetchColumn()) {
                throw new \RuntimeException('Esse profissional não realiza mais o serviço selecionado.');
            }

            $slots = (new AvailabilityService())->slots((int) $establishment['id'], $serviceId, $employeeId, $date);
            if (!in_array($time, $slots, true)) {
                throw new \RuntimeException('Esse horário não está mais disponível. Escolha outro.');
            }

            $serviceStmt = $pdo->prepare(
                'SELECT duration_minutes, price FROM services WHERE id = :service AND establishment_id = :establishment AND active = 1 LIMIT 1'
            );
            $serviceStmt->execute(['service' => $serviceId, 'establishment' => $establishment['id']]);
            $service = $serviceStmt->fetch();
            if (!$service) {
                throw new \RuntimeException('Serviço indisponível.');
            }

            $timezone = new DateTimeZone((string) $establishment['timezone']);
            $startsAt = new DateTimeImmutable($date . ' ' . $time . ':00', $timezone);
            $endsAt = $startsAt->add(new DateInterval('PT' . (int) $service['duration_minutes'] . 'M'));

            $insert = $pdo->prepare(
                'INSERT INTO appointments '
                . '(establishment_id, service_id, employee_user_id, client_user_id, created_by_user_id, starts_at, ends_at, status, price, notes) '
                . 'VALUES (:establishment, :service, :employee, :client, :creator, :starts, :ends, "confirmed", :price, :notes)'
            );
            $insert->execute([
                'establishment' => $establishment['id'],
                'service' => $serviceId,
                'employee' => $employeeId,
                'client' => Auth::id(),
                'creator' => Auth::id(),
                'starts' => $startsAt->format('Y-m-d H:i:s'),
                'ends' => $endsAt->format('Y-m-d H:i:s'),
                'price' => $service['price'],
                'notes' => $notes !== '' ? $notes : null,
            ]);

            $pdo->commit();
            flash('success', 'Agendamento confirmado com sucesso.');
            redirect('/painel');
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            flash('error', $exception instanceof \RuntimeException ? $exception->getMessage() : 'Não foi possível concluir o agendamento.');
            redirect('/estabelecimentos/' . $slug);
        }
    }
}
