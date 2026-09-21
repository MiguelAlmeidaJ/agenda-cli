<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\TenantContext;
use App\Core\View;
use App\Services\AppointmentRecurrenceService;
use App\Services\AvailabilityService;
use App\Services\EstablishmentClock;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;

final class AppointmentController
{
    public function create(): void
    {
        Auth::requireRole(['owner', 'employee']);
        $establishmentId = TenantContext::requireEstablishmentId();
        $pdo = Database::connection();
        $selectedCustomerId = (int) ($_GET['customer_id'] ?? 0);
        $clock = new EstablishmentClock();
        $today = $clock->now($pdo, $establishmentId)->format('Y-m-d');

        $customersStmt = $pdo->prepare(
            'SELECT id, name, email, phone FROM customers WHERE establishment_id = :establishment ORDER BY name LIMIT 500'
        );
        $customersStmt->execute(['establishment' => $establishmentId]);

        if (Auth::role() === 'employee') {
            $servicesStmt = $pdo->prepare(
                'SELECT s.id, s.name, s.duration_minutes, s.price FROM services s '
                . 'JOIN employee_services es ON es.service_id = s.id '
                . 'WHERE s.establishment_id = :establishment AND s.active = 1 AND es.employee_user_id = :user '
                . 'ORDER BY s.name'
            );
            $servicesStmt->execute(['establishment' => $establishmentId, 'user' => Auth::id()]);

            $providerStmt = $pdo->prepare(
                'SELECT es.service_id, u.id, u.name FROM employee_services es '
                . 'JOIN services s ON s.id = es.service_id '
                . 'JOIN users u ON u.id = es.employee_user_id '
                . 'WHERE s.establishment_id = :establishment AND s.active = 1 AND u.id = :user AND u.status = "active" '
                . 'ORDER BY s.name'
            );
            $providerStmt->execute(['establishment' => $establishmentId, 'user' => Auth::id()]);
        } else {
            $servicesStmt = $pdo->prepare(
                'SELECT id, name, duration_minutes, price FROM services '
                . 'WHERE establishment_id = :establishment AND active = 1 ORDER BY name'
            );
            $servicesStmt->execute(['establishment' => $establishmentId]);

            $providerStmt = $pdo->prepare(
                'SELECT es.service_id, u.id, u.name FROM employee_services es '
                . 'JOIN services s ON s.id = es.service_id '
                . 'JOIN establishments e ON e.id = s.establishment_id '
                . 'JOIN users u ON u.id = es.employee_user_id '
                . 'LEFT JOIN establishment_users eu ON eu.establishment_id = e.id AND eu.user_id = u.id '
                . 'WHERE e.id = :establishment AND s.active = 1 AND u.status = "active" '
                . 'AND (u.id = e.owner_user_id OR (eu.role = "employee" AND eu.active = 1)) '
                . 'ORDER BY u.name'
            );
            $providerStmt->execute(['establishment' => $establishmentId]);
        }

        $providersByService = [];
        foreach ($providerStmt->fetchAll() as $provider) {
            $providersByService[(int) $provider['service_id']][] = [
                'id' => (int) $provider['id'],
                'name' => (string) $provider['name'],
            ];
        }

        View::render('panel/appointment_create', [
            'title' => 'Novo agendamento',
            'customers' => $customersStmt->fetchAll(),
            'services' => $servicesStmt->fetchAll(),
            'providersByService' => $providersByService,
            'role' => Auth::role(),
            'selectedCustomerId' => $selectedCustomerId,
            'today' => $today,
        ]);
    }

    public function newAvailability(): void
    {
        Auth::requireRole(['owner', 'employee']);
        header('Content-Type: application/json; charset=utf-8');

        $establishmentId = TenantContext::requireEstablishmentId();
        $serviceId = (int) ($_GET['service_id'] ?? 0);
        $employeeId = (int) ($_GET['employee_id'] ?? 0);
        $date = trim((string) ($_GET['date'] ?? ''));

        if ($serviceId <= 0 || $date === '') {
            http_response_code(422);
            echo json_encode(['slots' => []], JSON_UNESCAPED_UNICODE);
            return;
        }

        if (Auth::role() === 'employee') {
            $employeeId = (int) Auth::id();
        }

        $availability = new AvailabilityService();
        if ($employeeId === 0 && Auth::role() === 'owner') {
            $slots = $availability->slotsForAnyProvider($establishmentId, $serviceId, $date);
        } else {
            if ($employeeId <= 0) {
                http_response_code(422);
                echo json_encode(['slots' => []], JSON_UNESCAPED_UNICODE);
                return;
            }
            $pdo = Database::connection();
            $nameStmt = $pdo->prepare('SELECT name FROM users WHERE id = :user LIMIT 1');
            $nameStmt->execute(['user' => $employeeId]);
            $name = (string) ($nameStmt->fetchColumn() ?: 'Profissional');
            $slots = array_map(
                static fn (string $time): array => [
                    'time' => $time,
                    'employee_id' => $employeeId,
                    'employee_name' => $name,
                ],
                $availability->slots($establishmentId, $serviceId, $employeeId, $date)
            );
        }

        echo json_encode(['slots' => $slots], JSON_UNESCAPED_UNICODE);
    }

    public function store(): void
    {
        Auth::requireRole(['owner', 'employee']);
        Csrf::validate($_POST['_csrf'] ?? null);

        $establishmentId = TenantContext::requireEstablishmentId();
        $customerId = (int) ($_POST['customer_id'] ?? 0);
        $serviceId = (int) ($_POST['service_id'] ?? 0);
        $employeeId = (int) ($_POST['employee_id'] ?? 0);
        $date = trim((string) ($_POST['date'] ?? ''));
        $time = trim((string) ($_POST['time'] ?? ''));
        $notes = trim((string) ($_POST['notes'] ?? ''));
        $recurring = isset($_POST['recurring']);
        $intervalWeeks = (int) ($_POST['interval_weeks'] ?? 1);
        $occurrences = (int) ($_POST['occurrences'] ?? 4);

        if (Auth::role() === 'employee') {
            $employeeId = (int) Auth::id();
        }

        if ($customerId <= 0 || $serviceId <= 0 || $employeeId <= 0 || $date === '' || $time === '') {
            flash('error', 'Selecione cliente, serviço, profissional, data e horário.');
            redirect('/painel/agendamentos/novo');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $customerStmt = $pdo->prepare(
                'SELECT id, user_id, name FROM customers WHERE id = :customer AND establishment_id = :establishment LIMIT 1 FOR UPDATE'
            );
            $customerStmt->execute(['customer' => $customerId, 'establishment' => $establishmentId]);
            $customer = $customerStmt->fetch();
            if (!$customer) {
                throw new \RuntimeException('Cliente não encontrado.');
            }

            $serviceStmt = $pdo->prepare(
                'SELECT s.duration_minutes, s.price, e.timezone FROM services s '
                . 'JOIN establishments e ON e.id = s.establishment_id '
                . 'WHERE s.id = :service AND s.establishment_id = :establishment AND s.active = 1 LIMIT 1'
            );
            $serviceStmt->execute(['service' => $serviceId, 'establishment' => $establishmentId]);
            $service = $serviceStmt->fetch();
            if (!$service) {
                throw new \RuntimeException('Serviço indisponível.');
            }

            $providerLock = $pdo->prepare('SELECT id FROM users WHERE id = :provider AND status = "active" FOR UPDATE');
            $providerLock->execute(['provider' => $employeeId]);
            if (!$providerLock->fetchColumn()) {
                throw new \RuntimeException('Profissional indisponível.');
            }

            $providerStmt = $pdo->prepare(
                'SELECT 1 FROM employee_services es '
                . 'JOIN services s ON s.id = es.service_id '
                . 'JOIN establishments e ON e.id = s.establishment_id '
                . 'LEFT JOIN establishment_users eu ON eu.establishment_id = e.id AND eu.user_id = es.employee_user_id '
                . 'WHERE e.id = :establishment AND s.id = :service AND es.employee_user_id = :provider '
                . 'AND (es.employee_user_id = e.owner_user_id OR (eu.role = "employee" AND eu.active = 1)) LIMIT 1'
            );
            $providerStmt->execute([
                'establishment' => $establishmentId,
                'service' => $serviceId,
                'provider' => $employeeId,
            ]);
            if (!$providerStmt->fetchColumn()) {
                throw new \RuntimeException('O profissional selecionado não realiza este serviço.');
            }

            $timezoneName = (string) $service['timezone'];
            $dates = $recurring
                ? (new AppointmentRecurrenceService())->dates($date, $intervalWeeks, $occurrences, $timezoneName)
                : [$date];

            $availability = new AvailabilityService();
            foreach ($dates as $occurrenceDate) {
                $slots = $availability->slots($establishmentId, $serviceId, $employeeId, $occurrenceDate);
                if (!in_array($time, $slots, true)) {
                    $formatted = (new DateTimeImmutable($occurrenceDate))->format('d/m/Y');
                    throw new \RuntimeException(
                        $recurring
                            ? 'A série não foi criada porque ' . $formatted . ' às ' . $time . ' não está disponível.'
                            : 'Esse horário não está mais disponível.'
                    );
                }
            }

            $seriesId = null;
            if ($recurring) {
                $series = $pdo->prepare(
                    'INSERT INTO appointment_series '
                    . '(establishment_id,customer_id,service_id,employee_user_id,created_by_user_id,frequency,interval_weeks,occurrences_count,starts_on,starts_at) '
                    . 'VALUES (:establishment,:customer,:service,:employee,:creator,"weekly",:interval,:occurrences,:starts_on,:starts_at)'
                );
                $series->execute([
                    'establishment' => $establishmentId,
                    'customer' => $customerId,
                    'service' => $serviceId,
                    'employee' => $employeeId,
                    'creator' => Auth::id(),
                    'interval' => $intervalWeeks,
                    'occurrences' => $occurrences,
                    'starts_on' => $date,
                    'starts_at' => $time . ':00',
                ]);
                $seriesId = (int) $pdo->lastInsertId();
            }

            $timezone = new DateTimeZone($timezoneName);
            $insert = $pdo->prepare(
                'INSERT INTO appointments '
                . '(establishment_id,service_id,employee_user_id,client_user_id,customer_id,series_id,series_position,created_by_user_id,starts_at,ends_at,status,price,notes) '
                . 'VALUES (:establishment,:service,:employee,:client,:customer,:series,:position,:creator,:starts,:ends,"confirmed",:price,:notes)'
            );

            $firstAppointmentId = 0;
            foreach ($dates as $index => $occurrenceDate) {
                $startsAt = new DateTimeImmutable($occurrenceDate . ' ' . $time . ':00', $timezone);
                $endsAt = $startsAt->add(new DateInterval('PT' . (int) $service['duration_minutes'] . 'M'));

                $insert->execute([
                    'establishment' => $establishmentId,
                    'service' => $serviceId,
                    'employee' => $employeeId,
                    'client' => $customer['user_id'] ?: null,
                    'customer' => $customerId,
                    'series' => $seriesId,
                    'position' => $seriesId !== null ? $index + 1 : null,
                    'creator' => Auth::id(),
                    'starts' => $startsAt->format('Y-m-d H:i:s'),
                    'ends' => $endsAt->format('Y-m-d H:i:s'),
                    'price' => $service['price'],
                    'notes' => $notes !== '' ? $notes : null,
                ]);

                $appointmentId = (int) $pdo->lastInsertId();
                if ($firstAppointmentId === 0) {
                    $firstAppointmentId = $appointmentId;
                }

                $details = $seriesId !== null
                    ? 'Agendamento recorrente ' . ($index + 1) . '/' . count($dates) . ' criado manualmente para ' . $customer['name'] . '.'
                    : 'Agendamento criado manualmente para ' . $customer['name'] . '.';

                $this->recordEvent(
                    $pdo,
                    $appointmentId,
                    $establishmentId,
                    'created',
                    null,
                    'confirmed',
                    $details
                );
            }

            $pdo->commit();
            flash(
                'success',
                $seriesId !== null
                    ? count($dates) . ' agendamentos recorrentes criados com sucesso.'
                    : 'Agendamento criado com sucesso.'
            );
            redirect('/painel/agendamentos/' . $firstAppointmentId . '/editar');
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            flash('error', $exception instanceof \RuntimeException ? $exception->getMessage() : 'Não foi possível criar o agendamento.');
            redirect('/painel/agendamentos/novo');
        }
    }

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

            if (in_array($targetStatus, ['completed', 'cancelled', 'no_show'], true)) {
                $clock = new EstablishmentClock();
                $invalidatedAt = $clock->sql($clock->now($pdo, $establishmentId));
                $pdo->prepare(
                    'UPDATE appointment_attendance_tokens SET used_at=:used '
                    . 'WHERE appointment_id=:appointment AND used_at IS NULL'
                )->execute(['used'=>$invalidatedAt,'appointment'=>$appointmentId]);
                $pdo->prepare(
                    'UPDATE notification_outbox SET status="cancelled" '
                    . 'WHERE appointment_id=:appointment AND status="pending" '
                    . 'AND event_type IN ("appointment_confirmation","reminder_24h","reminder_2h")'
                )->execute(['appointment'=>$appointmentId]);
            }

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

    public function cancelSeriesFrom(string $id): void
    {
        Auth::requireRole(['owner', 'employee']);
        Csrf::validate($_POST['_csrf'] ?? null);

        $appointmentId = (int) $id;
        $establishmentId = TenantContext::requireEstablishmentId();
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $appointment = $this->findForActor($pdo, $establishmentId, $appointmentId, true);
            if (!$appointment || empty($appointment['series_id'])) {
                throw new \RuntimeException('Este agendamento não pertence a uma série.');
            }

            $sql = 'SELECT id,status FROM appointments '
                . 'WHERE establishment_id=:establishment AND series_id=:series '
                . 'AND starts_at>=:starts AND status IN ("pending","confirmed")';
            $params = [
                'establishment' => $establishmentId,
                'series' => $appointment['series_id'],
                'starts' => $appointment['starts_at'],
            ];
            if (Auth::role() === 'employee') {
                $sql .= ' AND employee_user_id=:employee';
                $params['employee'] = Auth::id();
            }
            $sql .= ' ORDER BY starts_at FOR UPDATE';

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $future = $stmt->fetchAll();
            if ($future === []) {
                throw new \RuntimeException('Não há ocorrências futuras ativas nesta série.');
            }

            $clock = new EstablishmentClock();
            $invalidatedAt = $clock->sql($clock->now($pdo, $establishmentId));
            $update = $pdo->prepare(
                'UPDATE appointments SET status="cancelled" WHERE id=:id AND establishment_id=:establishment'
            );
            $cancelTokens = $pdo->prepare(
                'UPDATE appointment_attendance_tokens SET used_at=:used WHERE appointment_id=:appointment AND used_at IS NULL'
            );
            $cancelNotifications = $pdo->prepare(
                'UPDATE notification_outbox SET status="cancelled" '
                . 'WHERE appointment_id=:appointment AND status="pending" '
                . 'AND event_type IN ("appointment_confirmation","reminder_24h","reminder_2h")'
            );

            foreach ($future as $item) {
                $itemId = (int) $item['id'];
                $update->execute(['id' => $itemId, 'establishment' => $establishmentId]);
                $cancelTokens->execute(['used' => $invalidatedAt, 'appointment' => $itemId]);
                $cancelNotifications->execute(['appointment' => $itemId]);
                $this->recordEvent(
                    $pdo,
                    $itemId,
                    $establishmentId,
                    'status_changed',
                    (string) $item['status'],
                    'cancelled',
                    'Cancelado junto com as próximas ocorrências da série.'
                );
            }

            $pdo->commit();
            flash('success', count($future) . ' ocorrência(s) futura(s) da série cancelada(s).');
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            flash('error', $exception instanceof \RuntimeException ? $exception->getMessage() : 'Não foi possível cancelar a série.');
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

        $clock = new EstablishmentClock();
        $today = $clock->now($pdo, $establishmentId)->format('Y-m-d');

        View::render('panel/appointment_edit', [
            'title' => 'Reagendar atendimento',
            'appointment' => $appointment,
            'today' => $today,
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
                'UPDATE appointments SET employee_user_id = :employee, starts_at = :starts, ends_at = :ends, '
                . 'attendance_response = "pending", attendance_responded_at = NULL '
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

            $clock = new EstablishmentClock();
            $invalidatedAt = $clock->sql($clock->now($pdo, $establishmentId));
            $pdo->prepare(
                'UPDATE appointment_attendance_tokens SET used_at=:used '
                . 'WHERE appointment_id=:appointment AND used_at IS NULL'
            )->execute(['used'=>$invalidatedAt,'appointment'=>$appointmentId]);
            $pdo->prepare(
                'UPDATE notification_outbox SET status="cancelled" '
                . 'WHERE appointment_id=:appointment AND status="pending" '
                . 'AND event_type IN ("appointment_confirmation","reminder_24h","reminder_2h")'
            )->execute(['appointment'=>$appointmentId]);

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
        $sql = 'SELECT a.*, s.name AS service_name, s.duration_minutes, '
            . 'COALESCE(customer.name, client.name, "Cliente") AS client_name, '
            . 'employee.name AS employee_name, e.name AS establishment_name, e.slug AS establishment_slug, e.timezone, '
            . 'series.interval_weeks AS series_interval_weeks,series.occurrences_count AS series_occurrences_count '
            . 'FROM appointments a JOIN services s ON s.id = a.service_id '
            . 'LEFT JOIN customers customer ON customer.id = a.customer_id AND customer.establishment_id = a.establishment_id '
            . 'LEFT JOIN users client ON client.id = a.client_user_id '
            . 'LEFT JOIN appointment_series series ON series.id=a.series_id AND series.establishment_id=a.establishment_id '
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
