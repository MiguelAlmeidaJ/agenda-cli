<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\TenantContext;
use App\Core\View;
use App\Services\AvailabilityService;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;

final class AppointmentController
{
    public function status(string $id): void
    {
        Auth::requireRole(['owner', 'employee']);
        Csrf::validate($_POST['_csrf'] ?? null);

        $appointmentId = (int) $id;
        $targetStatus = (string) ($_POST['status'] ?? '');
        $establishmentId = TenantContext::requireEstablishmentId();
        $pdo = Database::connection();

        $pdo->beginTransaction();
        try {
            $appointment = $this->findForActor($pdo, $establishmentId, $appointmentId, true);
            if (!$appointment) {
                throw new \RuntimeException('Agendamento não encontrado.');
            }

            $allowed = [
                'pending' => ['confirmed', 'cancelled'],
                'confirmed' => ['completed', 'cancelled', 'no_show'],
                'completed' => [],
                'cancelled' => [],
                'no_show' => [],
            ];
            $currentStatus = (string) $appointment['status'];
            if (!in_array($targetStatus, $allowed[$currentStatus] ?? [], true)) {
                throw new \RuntimeException('Essa alteração de status não é permitida.');
            }

            $update = $pdo->prepare(
                'UPDATE appointments SET status = :status WHERE id = :id AND establishment_id = :establishment'
            );
            $update->execute([
                'status' => $targetStatus,
                'id' => $appointmentId,
                'establishment' => $establishmentId,
            ]);

            $this->recordEvent($pdo, $appointmentId, $establishmentId, 'status_changed', $currentStatus, $targetStatus);
            $pdo->commit();
            flash('success', 'Status do agendamento atualizado.');
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            flash('error', $exception instanceof \RuntimeException ? $exception->getMessage() : 'Não foi possível atualizar o agendamento.');
        }

        redirect('/painel/agendamentos');
    }

    public function edit(string $id): void
    {
        Auth::requireRole(['owner', 'employee']);
        $establishmentId = TenantContext::requireEstablishmentId();
        $appointmentId = (int) $id;
        $pdo = Database::connection();

        $appointment = $this->findForActor($pdo, $establishmentId, $appointmentId);
        if (!$appointment) {
            http_response_code(404);
            View::render('errors/404', ['title' => 'Agendamento não encontrado']);
            return;
        }

        if (Auth::role() === 'employee') {
            $providers = $pdo->prepare(
                'SELECT u.id, u.name FROM employee_services es JOIN users u ON u.id = es.employee_user_id '
                . 'WHERE es.service_id = :service AND u.id = :user AND u.status = "active" LIMIT 1'
            );
            $providers->execute(['service' => $appointment['service_id'], 'user' => Auth::id()]);
        } else {
            $providers = $pdo->prepare(
                'SELECT u.id, u.name FROM employee_services es '
                . 'JOIN users u ON u.id = es.employee_user_id '
                . 'JOIN establishments e ON e.id = :establishment '
                . 'LEFT JOIN establishment_users eu ON eu.establishment_id = e.id AND eu.user_id = u.id '
                . 'WHERE es.service_id = :service AND u.status = "active" '
                . 'AND (u.id = e.owner_user_id OR (eu.role = "employee" AND eu.active = 1)) '
                . 'ORDER BY u.name'
            );
            $providers->execute([
                'establishment' => $establishmentId,
                'service' => $appointment['service_id'],
            ]);
        }

        $events = $pdo->prepare(
            'SELECT ae.*, u.name AS user_name FROM appointment_events ae '
            . 'LEFT JOIN users u ON u.id = ae.user_id '
            . 'WHERE ae.appointment_id = :appointment AND ae.establishment_id = :establishment '
            . 'ORDER BY ae.created_at DESC LIMIT 20'
        );
        $events->execute(['appointment' => $appointmentId, 'establishment' => $establishmentId]);

        View::render('panel/appointment_edit', [
            'title' => 'Reagendar atendimento',
            'appointment' => $appointment,
            'providers' => $providers->fetchAll(),
            'events' => $events->fetchAll(),
        ]);
    }

    public function availability(string $id): void
    {
        Auth::requireRole(['owner', 'employee']);
        header('Content-Type: application/json; charset=utf-8');

        $appointmentId = (int) $id;
        $establishmentId = TenantContext::requireEstablishmentId();
        $employeeId = (int) ($_GET['employee_id'] ?? 0);
        $date = trim((string) ($_GET['date'] ?? ''));
        $pdo = Database::connection();

        $appointment = $this->findForActor($pdo, $establishmentId, $appointmentId);
        if (!$appointment || $employeeId <= 0 || $date === '') {
            http_response_code(422);
            echo json_encode(['slots' => []], JSON_UNESCAPED_UNICODE);
            return;
        }
        if (Auth::role() === 'employee' && $employeeId !== (int) Auth::id()) {
            http_response_code(403);
            echo json_encode(['slots' => []], JSON_UNESCAPED_UNICODE);
            return;
        }

        $slots = (new AvailabilityService())->slots(
            $establishmentId,
            (int) $appointment['service_id'],
            $employeeId,
            $date,
            $appointmentId
        );
        echo json_encode(['slots' => $slots], JSON_UNESCAPED_UNICODE);
    }

    public function update(string $id): void
    {
        Auth::requireRole(['owner', 'employee']);
        Csrf::validate($_POST['_csrf'] ?? null);

        $appointmentId = (int) $id;
        $establishmentId = TenantContext::requireEstablishmentId();
        $employeeId = (int) ($_POST['employee_id'] ?? 0);
        $date = trim((string) ($_POST['date'] ?? ''));
        $time = trim((string) ($_POST['time'] ?? ''));
        $pdo = Database::connection();

        $pdo->beginTransaction();
        try {
            $appointment = $this->findForActor($pdo, $establishmentId, $appointmentId, true);
            if (!$appointment) {
                throw new \RuntimeException('Agendamento não encontrado.');
            }
            if (!in_array($appointment['status'], ['pending', 'confirmed'], true)) {
                throw new \RuntimeException('Esse agendamento não pode mais ser reagendado.');
            }
            if ($employeeId <= 0 || $date === '' || $time === '') {
                throw new \RuntimeException('Selecione profissional, data e horário.');
            }
            if (Auth::role() === 'employee' && $employeeId !== (int) Auth::id()) {
                throw new \RuntimeException('Você só pode reagendar atendimentos para a sua própria agenda.');
            }

            $providerLock = $pdo->prepare('SELECT id FROM users WHERE id = :provider AND status = "active" FOR UPDATE');
            $providerLock->execute(['provider' => $employeeId]);
            if (!$providerLock->fetchColumn()) {
                throw new \RuntimeException('Profissional indisponível.');
            }

            $slots = (new AvailabilityService())->slots(
                $establishmentId,
                (int) $appointment['service_id'],
                $employeeId,
                $date,
                $appointmentId
            );
            if (!in_array($time, $slots, true)) {
                throw new \RuntimeException('Esse horário não está disponível.');
            }

            $service = $pdo->prepare('SELECT duration_minutes FROM services WHERE id = :service AND establishment_id = :establishment LIMIT 1');
            $service->execute(['service' => $appointment['service_id'], 'establishment' => $establishmentId]);
            $duration = (int) ($service->fetchColumn() ?: 0);
            if ($duration <= 0) {
                throw new \RuntimeException('Serviço indisponível.');
            }

            $timezone = new DateTimeZone((string) $appointment['timezone']);
            $startsAt = new DateTimeImmutable($date . ' ' . $time . ':00', $timezone);
            $endsAt = $startsAt->add(new DateInterval('PT' . $duration . 'M'));
            $oldDescription = date('d/m/Y H:i', strtotime((string) $appointment['starts_at'])) . ' · ' . $appointment['employee_name'];

            $providerNameStmt = $pdo->prepare('SELECT name FROM users WHERE id = :user LIMIT 1');
            $providerNameStmt->execute(['user' => $employeeId]);
            $providerName = (string) ($providerNameStmt->fetchColumn() ?: 'Profissional');

            $update = $pdo->prepare(
                'UPDATE appointments SET employee_user_id = :employee, starts_at = :starts, ends_at = :ends '
                . 'WHERE id = :id AND establishment_id = :establishment'
            );
            $update->execute([
                'employee' => $employeeId,
                'starts' => $startsAt->format('Y-m-d H:i:s'),
                'ends' => $endsAt->format('Y-m-d H:i:s'),
                'id' => $appointmentId,
                'establishment' => $establishmentId,
            ]);

            $newDescription = $startsAt->format('d/m/Y H:i') . ' · ' . $providerName;
            $this->recordEvent(
                $pdo,
                $appointmentId,
                $establishmentId,
                'rescheduled',
                null,
                null,
                $oldDescription . ' → ' . $newDescription
            );

            $pdo->commit();
            flash('success', 'Agendamento reagendado com sucesso.');
            redirect('/painel/agendamentos/' . $appointmentId . '/editar');
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            flash('error', $exception instanceof \RuntimeException ? $exception->getMessage() : 'Não foi possível reagendar.');
            redirect('/painel/agendamentos/' . $appointmentId . '/editar');
        }
    }

    private function findForActor(\PDO $pdo, int $establishmentId, int $appointmentId, bool $forUpdate = false): ?array
    {
        $sql = 'SELECT a.*, s.name AS service_name, s.duration_minutes, client.name AS client_name, '
            . 'employee.name AS employee_name, e.name AS establishment_name, e.slug AS establishment_slug, e.timezone '
            . 'FROM appointments a JOIN services s ON s.id = a.service_id '
            . 'JOIN users client ON client.id = a.client_user_id '
            . 'JOIN users employee ON employee.id = a.employee_user_id '
            . 'JOIN establishments e ON e.id = a.establishment_id '
            . 'WHERE a.id = :id AND a.establishment_id = :establishment ';
        $params = ['id' => $appointmentId, 'establishment' => $establishmentId];

        if (Auth::role() === 'employee') {
            $sql .= 'AND a.employee_user_id = :user ';
            $params['user'] = Auth::id();
        }
        $sql .= 'LIMIT 1';
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $appointment = $stmt->fetch();
        return $appointment ?: null;
    }

    private function recordEvent(
        \PDO $pdo,
        int $appointmentId,
        int $establishmentId,
        string $eventType,
        ?string $fromStatus = null,
        ?string $toStatus = null,
        ?string $details = null
    ): void {
        $event = $pdo->prepare(
            'INSERT INTO appointment_events (appointment_id, establishment_id, user_id, event_type, from_status, to_status, details) '
            . 'VALUES (:appointment, :establishment, :user, :event, :from_status, :to_status, :details)'
        );
        $event->execute([
            'appointment' => $appointmentId,
            'establishment' => $establishmentId,
            'user' => Auth::id(),
            'event' => $eventType,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'details' => $details,
        ]);
    }
}
