<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\View;
use App\Services\AvailabilityService;
use App\Services\CustomerService;
use App\Services\EstablishmentClock;
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
        $date = trim((string) ($_GET['date'] ?? ''));

        if ($establishmentId === 0 || $serviceId === 0 || $date === '') {
            http_response_code(422);
            echo json_encode(['slots' => []], JSON_UNESCAPED_UNICODE);
            return;
        }

        $availability = new AvailabilityService();
        if ($employeeId === 0) {
            $slots = $availability->slotsForAnyProvider($establishmentId, $serviceId, $date);
        } else {
            $nameStmt = $pdo->prepare('SELECT name FROM users WHERE id = :provider LIMIT 1');
            $nameStmt->execute(['provider' => $employeeId]);
            $providerName = (string) ($nameStmt->fetchColumn() ?: 'Profissional');
            $slots = array_map(
                static fn (string $time): array => [
                    'time' => $time,
                    'employee_id' => $employeeId,
                    'employee_name' => $providerName,
                ],
                $availability->slots($establishmentId, $serviceId, $employeeId, $date)
            );
        }

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
            $lock = $pdo->prepare('SELECT id FROM users WHERE id = :provider AND status = "active" FOR UPDATE');
            $lock->execute(['provider' => $employeeId]);
            if (!$lock->fetchColumn()) {
                throw new \RuntimeException('Profissional indisponível.');
            }

            $provider = $pdo->prepare(
                'SELECT 1 FROM employee_services es JOIN services s ON s.id = es.service_id '
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

            $customerId = (new CustomerService())->ensureForUser($pdo, (int) $establishment['id'], (int) Auth::id());
            $timezone = new DateTimeZone((string) $establishment['timezone']);
            $startsAt = new DateTimeImmutable($date . ' ' . $time . ':00', $timezone);
            $endsAt = $startsAt->add(new DateInterval('PT' . (int) $service['duration_minutes'] . 'M'));

            $insert = $pdo->prepare(
                'INSERT INTO appointments '
                . '(establishment_id, service_id, employee_user_id, client_user_id, customer_id, created_by_user_id, starts_at, ends_at, status, price, notes) '
                . 'VALUES (:establishment, :service, :employee, :client, :customer, :creator, :starts, :ends, "confirmed", :price, :notes)'
            );
            $insert->execute([
                'establishment' => $establishment['id'],
                'service' => $serviceId,
                'employee' => $employeeId,
                'client' => Auth::id(),
                'customer' => $customerId,
                'creator' => Auth::id(),
                'starts' => $startsAt->format('Y-m-d H:i:s'),
                'ends' => $endsAt->format('Y-m-d H:i:s'),
                'price' => $service['price'],
                'notes' => $notes !== '' ? $notes : null,
            ]);
            $appointmentId = (int) $pdo->lastInsertId();

            $event = $pdo->prepare(
                'INSERT INTO appointment_events (appointment_id, establishment_id, user_id, event_type, to_status, details) '
                . 'VALUES (:appointment, :establishment, :user, "created", "confirmed", :details)'
            );
            $event->execute([
                'appointment' => $appointmentId,
                'establishment' => $establishment['id'],
                'user' => Auth::id(),
                'details' => 'Agendamento criado pelo cliente.',
            ]);

            $pdo->commit();
            flash('success', 'Agendamento confirmado com sucesso.');
            redirect('/painel');
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            flash('error', $exception instanceof \RuntimeException ? $exception->getMessage() : 'Não foi possível concluir o agendamento.');
            redirect('/estabelecimentos/' . $slug);
        }
    }


    public function rescheduleForm(string $id): void
    {
        Auth::requireRole(['client']);
        $pdo = Database::connection();
        $appointment = $this->clientAppointment($pdo, (int) $id);
        if (!$appointment) {
            http_response_code(404);
            View::render('errors/404', ['title' => 'Agendamento não encontrado']);
            return;
        }

        try {
            $this->assertClientCanReschedule($appointment);
        } catch (\RuntimeException $exception) {
            flash('error', $exception->getMessage());
            redirect('/painel/agendamentos');
        }

        $providers = $pdo->prepare(
            'SELECT u.id,u.name FROM employee_services es '
            . 'JOIN users u ON u.id=es.employee_user_id '
            . 'JOIN establishments e ON e.id=:establishment '
            . 'LEFT JOIN establishment_users eu ON eu.establishment_id=e.id AND eu.user_id=u.id '
            . 'WHERE es.service_id=:service AND u.status="active" '
            . 'AND (u.id=e.owner_user_id OR (eu.role="employee" AND eu.active=1)) ORDER BY u.name'
        );
        $providers->execute([
            'establishment' => $appointment['establishment_id'],
            'service' => $appointment['service_id'],
        ]);

        $clock = new EstablishmentClock();
        $todayDate = $clock->inTimezone((string) $appointment['timezone'])->setTime(0, 0, 0);

        View::render('panel/client_appointment_reschedule', [
            'title' => 'Reagendar atendimento',
            'appointment' => $appointment,
            'providers' => $providers->fetchAll(),
            'today' => $todayDate->format('Y-m-d'),
            'maxDate' => $todayDate->modify('+' . (int) $appointment['max_advance_days'] . ' days')->format('Y-m-d'),
        ]);
    }

    public function rescheduleAvailability(string $id): void
    {
        Auth::requireRole(['client']);
        header('Content-Type: application/json; charset=utf-8');

        $appointmentId = (int) $id;
        $employeeId = (int) ($_GET['employee_id'] ?? 0);
        $date = trim((string) ($_GET['date'] ?? ''));
        $appointment = $this->clientAppointment(Database::connection(), $appointmentId);

        try {
            if (!$appointment || $employeeId <= 0 || $date === '') {
                throw new \RuntimeException('Dados incompletos.');
            }
            $this->assertClientCanReschedule($appointment);
            $slots = (new AvailabilityService())->slots(
                (int) $appointment['establishment_id'],
                (int) $appointment['service_id'],
                $employeeId,
                $date,
                $appointmentId
            );
            echo json_encode(['slots' => $slots], JSON_UNESCAPED_UNICODE);
        } catch (\RuntimeException $exception) {
            http_response_code(422);
            echo json_encode(['slots' => [], 'error' => $exception->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }

    public function reschedule(string $id): void
    {
        Auth::requireRole(['client']);
        Csrf::validate($_POST['_csrf'] ?? null);

        $appointmentId = (int) $id;
        $employeeId = (int) ($_POST['employee_id'] ?? 0);
        $date = trim((string) ($_POST['date'] ?? ''));
        $time = trim((string) ($_POST['time'] ?? ''));
        $pdo = Database::connection();

        $pdo->beginTransaction();
        try {
            $appointment = $this->clientAppointment($pdo, $appointmentId, true);
            if (!$appointment) {
                throw new \RuntimeException('Agendamento não encontrado.');
            }
            $this->assertClientCanReschedule($appointment);
            if ($employeeId <= 0 || $date === '' || $time === '') {
                throw new \RuntimeException('Selecione profissional, data e horário.');
            }

            $providerLock = $pdo->prepare('SELECT id FROM users WHERE id=:provider AND status="active" FOR UPDATE');
            $providerLock->execute(['provider' => $employeeId]);
            if (!$providerLock->fetchColumn()) {
                throw new \RuntimeException('Profissional indisponível.');
            }

            $slots = (new AvailabilityService())->slots(
                (int) $appointment['establishment_id'],
                (int) $appointment['service_id'],
                $employeeId,
                $date,
                $appointmentId
            );
            if (!in_array($time, $slots, true)) {
                throw new \RuntimeException('Esse horário não está mais disponível.');
            }

            $timezone = new DateTimeZone((string) $appointment['timezone']);
            $startsAt = new DateTimeImmutable($date . ' ' . $time . ':00', $timezone);
            $endsAt = $startsAt->add(new DateInterval('PT' . (int) $appointment['duration_minutes'] . 'M'));
            $oldDescription = date('d/m/Y H:i', strtotime((string) $appointment['starts_at'])) . ' · ' . $appointment['employee_name'];

            $providerNameStmt = $pdo->prepare('SELECT name FROM users WHERE id=:id LIMIT 1');
            $providerNameStmt->execute(['id' => $employeeId]);
            $providerName = (string) ($providerNameStmt->fetchColumn() ?: 'Profissional');

            $pdo->prepare(
                'UPDATE appointments SET employee_user_id=:employee,starts_at=:starts,ends_at=:ends,'
                . 'attendance_response="pending",attendance_responded_at=NULL '
                . 'WHERE id=:id AND client_user_id=:client'
            )->execute([
                'employee' => $employeeId,
                'starts' => $startsAt->format('Y-m-d H:i:s'),
                'ends' => $endsAt->format('Y-m-d H:i:s'),
                'id' => $appointmentId,
                'client' => Auth::id(),
            ]);

            $pdo->prepare(
                'INSERT INTO appointment_events (appointment_id,establishment_id,user_id,event_type,details) '
                . 'VALUES (:appointment,:establishment,:user,"rescheduled",:details)'
            )->execute([
                'appointment' => $appointmentId,
                'establishment' => $appointment['establishment_id'],
                'user' => Auth::id(),
                'details' => $oldDescription . ' → ' . $startsAt->format('d/m/Y H:i') . ' · ' . $providerName . ' (cliente)',
            ]);

            $pdo->prepare(
                'UPDATE notification_outbox SET status="cancelled" '
                . 'WHERE appointment_id=:appointment AND status="pending" '
                . 'AND event_type IN ("appointment_confirmation","reminder_24h","reminder_2h")'
            )->execute(['appointment' => $appointmentId]);

            $clock = new EstablishmentClock();
            $invalidatedAt = $clock->sql($clock->inTimezone((string) $appointment['timezone']));
            $pdo->prepare(
                'UPDATE appointment_attendance_tokens SET used_at=:used '
                . 'WHERE appointment_id=:appointment AND used_at IS NULL'
            )->execute(['used'=>$invalidatedAt,'appointment'=>$appointmentId]);

            $pdo->commit();
            flash('success', 'Agendamento reagendado com sucesso.');
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            flash('error', $exception instanceof \RuntimeException ? $exception->getMessage() : 'Não foi possível reagendar o atendimento.');
        }

        redirect('/painel/agendamentos');
    }

    public function confirmAttendance(string $id): void
    {
        Auth::requireRole(['client']);
        Csrf::validate($_POST['_csrf'] ?? null);

        $appointmentId = (int) $id;
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $appointment = $this->clientAppointment($pdo, $appointmentId, true);
            if (!$appointment) {
                throw new \RuntimeException('Agendamento não encontrado.');
            }
            if (!in_array($appointment['status'], ['pending', 'confirmed'], true)) {
                throw new \RuntimeException('Este agendamento não está mais disponível para confirmação de presença.');
            }

            $clock = new EstablishmentClock();
            $now = $clock->inTimezone((string) $appointment['timezone']);
            $startsAt = $clock->inTimezone((string) $appointment['timezone'], (string) $appointment['starts_at']);
            if ($startsAt <= $now) {
                throw new \RuntimeException('O atendimento já começou ou está no passado.');
            }

            if (($appointment['attendance_response'] ?? 'pending') !== 'confirmed') {
                $pdo->prepare(
                    'UPDATE appointments SET attendance_response="confirmed",attendance_responded_at=:responded '
                    . 'WHERE id=:appointment AND client_user_id=:client'
                )->execute([
                    'responded' => $clock->sql($now),
                    'appointment' => $appointmentId,
                    'client' => Auth::id(),
                ]);

                $pdo->prepare(
                    'INSERT INTO appointment_events '
                    . '(appointment_id,establishment_id,user_id,event_type,details) '
                    . 'VALUES (:appointment,:establishment,:user,"attendance_confirmed",:details)'
                )->execute([
                    'appointment' => $appointmentId,
                    'establishment' => $appointment['establishment_id'],
                    'user' => Auth::id(),
                    'details' => 'Presença confirmada pelo cliente na área autenticada.',
                ]);
            }

            $pdo->commit();
            flash('success', 'Sua presença foi confirmada.');
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            flash('error', $exception instanceof \RuntimeException
                ? $exception->getMessage()
                : 'Não foi possível confirmar sua presença.');
        }

        redirect('/painel/agendamentos');
    }

    public function cancel(string $id): void
    {
        Auth::requireRole(['client']);
        Csrf::validate($_POST['_csrf'] ?? null);
        $appointmentId = (int) $id;
        $pdo = Database::connection();

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'SELECT a.id, a.establishment_id, a.starts_at, a.status, e.timezone, '
                . 'COALESCE(bs.cancellation_notice_minutes, 0) AS cancellation_notice_minutes '
                . 'FROM appointments a JOIN establishments e ON e.id = a.establishment_id '
                . 'LEFT JOIN booking_settings bs ON bs.establishment_id = a.establishment_id '
                . 'WHERE a.id = :id AND a.client_user_id = :client LIMIT 1 FOR UPDATE'
            );
            $stmt->execute(['id' => $appointmentId, 'client' => Auth::id()]);
            $appointment = $stmt->fetch();
            if (!$appointment) throw new \RuntimeException('Agendamento não encontrado.');
            if (!in_array($appointment['status'], ['pending', 'confirmed'], true)) {
                throw new \RuntimeException('Este agendamento não pode mais ser cancelado.');
            }

            $timezone = new DateTimeZone((string) $appointment['timezone']);
            $startsAt = new DateTimeImmutable((string) $appointment['starts_at'], $timezone);
            $deadline = $startsAt->modify('-' . (int) $appointment['cancellation_notice_minutes'] . ' minutes');
            if (new DateTimeImmutable('now', $timezone) > $deadline) {
                throw new \RuntimeException('O prazo para cancelamento online deste agendamento já encerrou. Entre em contato com o estabelecimento.');
            }

            $update = $pdo->prepare('UPDATE appointments SET status = "cancelled" WHERE id = :id AND client_user_id = :client');
            $update->execute(['id' => $appointmentId, 'client' => Auth::id()]);

            $clock = new EstablishmentClock();
            $invalidatedAt = $clock->sql($clock->inTimezone((string) $appointment['timezone']));
            $pdo->prepare(
                'UPDATE appointment_attendance_tokens SET used_at=:used '
                . 'WHERE appointment_id=:appointment AND used_at IS NULL'
            )->execute(['used'=>$invalidatedAt,'appointment'=>$appointmentId]);
            $pdo->prepare(
                'UPDATE notification_outbox SET status="cancelled" '
                . 'WHERE appointment_id=:appointment AND status="pending" '
                . 'AND event_type IN ("appointment_confirmation","reminder_24h","reminder_2h")'
            )->execute(['appointment'=>$appointmentId]);

            $event = $pdo->prepare(
                'INSERT INTO appointment_events (appointment_id, establishment_id, user_id, event_type, from_status, to_status, details) '
                . 'VALUES (:appointment, :establishment, :user, "status_changed", :from_status, "cancelled", :details)'
            );
            $event->execute([
                'appointment' => $appointmentId,
                'establishment' => $appointment['establishment_id'],
                'user' => Auth::id(),
                'from_status' => $appointment['status'],
                'details' => 'Cancelado pelo cliente.',
            ]);
            $pdo->commit();
            flash('success', 'Agendamento cancelado.');
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            flash('error', $exception instanceof \RuntimeException ? $exception->getMessage() : 'Não foi possível cancelar o agendamento.');
        }
        redirect('/painel/agendamentos');
    }
    private function clientAppointment(\PDO $pdo, int $appointmentId, bool $forUpdate = false): ?array
    {
        $sql = 'SELECT a.*,e.name establishment_name,e.slug establishment_slug,e.timezone,'
            . 's.name service_name,s.duration_minutes,employee.name employee_name,'
            . 'COALESCE(bs.reschedule_notice_minutes,0) reschedule_notice_minutes,'
            . 'COALESCE(bs.max_advance_days,90) max_advance_days '
            . 'FROM appointments a JOIN establishments e ON e.id=a.establishment_id '
            . 'JOIN services s ON s.id=a.service_id JOIN users employee ON employee.id=a.employee_user_id '
            . 'LEFT JOIN booking_settings bs ON bs.establishment_id=a.establishment_id '
            . 'WHERE a.id=:id AND a.client_user_id=:client LIMIT 1';
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $appointmentId, 'client' => Auth::id()]);
        $appointment = $stmt->fetch();
        return $appointment ?: null;
    }

    private function assertClientCanReschedule(array $appointment): void
    {
        if (!in_array($appointment['status'], ['pending', 'confirmed'], true)) {
            throw new \RuntimeException('Este agendamento não pode mais ser reagendado.');
        }

        $timezone = new DateTimeZone((string) $appointment['timezone']);
        $startsAt = new DateTimeImmutable((string) $appointment['starts_at'], $timezone);
        $now = new DateTimeImmutable('now', $timezone);
        if ($startsAt <= $now) {
            throw new \RuntimeException('Este atendimento já começou ou está no passado.');
        }

        $deadline = $startsAt->modify('-' . (int) $appointment['reschedule_notice_minutes'] . ' minutes');
        if ($now > $deadline) {
            throw new \RuntimeException('O prazo para reagendamento online já encerrou. Entre em contato com o estabelecimento.');
        }
    }

}
