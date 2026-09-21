<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Csrf;
use App\Core\Database;
use App\Core\View;
use App\Services\EstablishmentClock;

final class AttendanceConfirmationController
{
    public function show(string $token): void
    {
        $record = $this->find($token, false);
        if ($record === null) {
            $this->notFound();
            return;
        }

        View::render('attendance_confirmation', [
            'title' => 'Confirmar presença',
            'appointment' => $record,
            'token' => $token,
            'state' => $this->state($record),
        ]);
    }

    public function confirm(string $token): void
    {
        Csrf::validate($_POST['_csrf'] ?? null);

        if (!$this->validToken($token)) {
            $this->notFound();
            return;
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $hash = hash('sha256', $token);
            $stmt = $pdo->prepare(
                'SELECT t.id token_id,t.expires_at,t.used_at,a.id appointment_id,a.establishment_id,'
                . 'a.starts_at,a.status,a.attendance_response,a.attendance_responded_at,e.timezone '
                . 'FROM appointment_attendance_tokens t '
                . 'JOIN appointments a ON a.id=t.appointment_id AND a.establishment_id=t.establishment_id '
                . 'JOIN establishments e ON e.id=a.establishment_id '
                . 'WHERE t.token_hash=:hash LIMIT 1 FOR UPDATE'
            );
            $stmt->execute(['hash' => $hash]);
            $record = $stmt->fetch();

            if (!$record) {
                throw new \RuntimeException('Link de confirmação não encontrado.');
            }

            $state = $this->state($record);
            if ($state === 'confirmed') {
                $pdo->commit();
                redirect('/agendamentos/presenca/' . $token);
            }
            if ($state !== 'available') {
                throw new \RuntimeException('Este link de confirmação expirou ou o agendamento não está mais ativo.');
            }

            $clock = new EstablishmentClock();
            $now = $clock->inTimezone((string) $record['timezone']);
            $nowSql = $clock->sql($now);

            $pdo->prepare(
                'UPDATE appointments SET attendance_response="confirmed",attendance_responded_at=:responded '
                . 'WHERE id=:appointment AND establishment_id=:establishment'
            )->execute([
                'responded' => $nowSql,
                'appointment' => $record['appointment_id'],
                'establishment' => $record['establishment_id'],
            ]);

            $pdo->prepare(
                'INSERT INTO appointment_events '
                . '(appointment_id,establishment_id,user_id,event_type,details) '
                . 'VALUES (:appointment,:establishment,NULL,"attendance_confirmed",:details)'
            )->execute([
                'appointment' => $record['appointment_id'],
                'establishment' => $record['establishment_id'],
                'details' => 'Presença confirmada pelo cliente através do link de confirmação.',
            ]);

            $pdo->commit();
            redirect('/agendamentos/presenca/' . $token);
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            flash('error', $exception instanceof \RuntimeException
                ? $exception->getMessage()
                : 'Não foi possível confirmar sua presença.');
            redirect('/agendamentos/presenca/' . $token);
        }
    }

    private function find(string $token, bool $forUpdate): ?array
    {
        if (!$this->validToken($token)) {
            return null;
        }

        $sql = 'SELECT t.id token_id,t.expires_at,t.used_at,a.id appointment_id,a.establishment_id,'
            . 'a.starts_at,a.ends_at,a.status,a.attendance_response,a.attendance_responded_at,'
            . 'e.name establishment_name,e.slug establishment_slug,e.timezone,'
            . 's.name service_name,u.name employee_name '
            . 'FROM appointment_attendance_tokens t '
            . 'JOIN appointments a ON a.id=t.appointment_id AND a.establishment_id=t.establishment_id '
            . 'JOIN establishments e ON e.id=a.establishment_id '
            . 'JOIN services s ON s.id=a.service_id '
            . 'JOIN users u ON u.id=a.employee_user_id '
            . 'WHERE t.token_hash=:hash LIMIT 1';

        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['hash' => hash('sha256', $token)]);
        $record = $stmt->fetch();

        return $record ?: null;
    }

    private function state(array $record): string
    {
        if (!in_array((string) ($record['status'] ?? ''), ['pending', 'confirmed'], true)) {
            return 'unavailable';
        }

        $clock = new EstablishmentClock();
        $timezone = (string) ($record['timezone'] ?? env('APP_TIMEZONE', 'America/Sao_Paulo'));
        $now = $clock->inTimezone($timezone);
        $startsAt = $clock->inTimezone($timezone, (string) $record['starts_at']);
        $expiresAt = $clock->inTimezone($timezone, (string) $record['expires_at']);

        if ($startsAt <= $now || $expiresAt <= $now) {
            return 'expired';
        }

        if (!empty($record['used_at'])) {
            return 'expired';
        }

        if (($record['attendance_response'] ?? null) === 'confirmed') {
            return 'confirmed';
        }

        return 'available';
    }

    private function validToken(string $token): bool
    {
        return preg_match('/^[a-f0-9]{64}$/', $token) === 1;
    }

    private function notFound(): void
    {
        http_response_code(404);
        View::render('errors/404', ['title' => 'Confirmação não encontrada']);
    }
}
