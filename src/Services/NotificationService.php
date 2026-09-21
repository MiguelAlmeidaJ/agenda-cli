<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class NotificationService
{
    public function settings(int $establishmentId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT ns.*,COALESCE(bs.waitlist_offer_minutes,30) waitlist_offer_minutes '
            . 'FROM notification_settings ns LEFT JOIN booking_settings bs ON bs.establishment_id=ns.establishment_id '
            . 'WHERE ns.establishment_id=:id LIMIT 1'
        );
        $stmt->execute(['id' => $establishmentId]);
        return $stmt->fetch() ?: [
            'establishment_id' => $establishmentId,
            'whatsapp_enabled' => 0,
            'provider' => null,
            'confirmation_enabled' => 1,
            'cancellation_enabled' => 1,
            'reminder_24h_enabled' => 1,
            'reminder_2h_enabled' => 0,
            'waitlist_enabled' => 1,
            'waitlist_offer_minutes' => 30,
        ];
    }

    public function scheduleEstablishment(int $establishmentId): array
    {
        $settings = $this->settings($establishmentId);
        $result = ['confirmation' => 0, 'cancellation' => 0, 'reminder_24h' => 0, 'reminder_2h' => 0];
        if ((int) $settings['whatsapp_enabled'] !== 1) return $result;

        $pdo = Database::connection();
        $clock = new EstablishmentClock();
        $now = $clock->now($pdo, $establishmentId);
        $recentEpoch = time() - (7 * 86400);

        $rules = [
            'confirmation' => [
                (int) $settings['confirmation_enabled'],
                'a.status="confirmed" AND a.starts_at>:now AND (UNIX_TIMESTAMP(a.created_at)>=:created_recent_epoch OR UNIX_TIMESTAMP(a.updated_at)>=:updated_recent_epoch)',
                'appointment_confirmation',
                [
                    'now'=>$clock->sql($now),
                    'created_recent_epoch'=>$recentEpoch,
                    'updated_recent_epoch'=>$recentEpoch,
                ],
            ],
            'cancellation' => [
                (int) $settings['cancellation_enabled'],
                'a.status="cancelled" AND UNIX_TIMESTAMP(a.updated_at)>=:recent_epoch',
                'appointment_cancelled',
                ['recent_epoch'=>$recentEpoch],
            ],
            'reminder_24h' => [
                (int) $settings['reminder_24h_enabled'],
                'a.status="confirmed" AND a.starts_at BETWEEN :window_start AND :window_end',
                'reminder_24h',
                ['window_start'=>$clock->sql($now->modify('+23 hours')),'window_end'=>$clock->sql($now->modify('+25 hours'))],
            ],
            'reminder_2h' => [
                (int) $settings['reminder_2h_enabled'],
                'a.status="confirmed" AND a.starts_at BETWEEN :window_start AND :window_end',
                'reminder_2h',
                ['window_start'=>$clock->sql($now->modify('+90 minutes')),'window_end'=>$clock->sql($now->modify('+150 minutes'))],
            ],
        ];
        foreach ($rules as $key => [$enabled, $condition, $event, $params]) {
            if ($enabled === 1) $result[$key] = $this->queueAppointments($establishmentId, $condition, $event, $params);
        }
        return $result;
    }

    public function queueWaitlistMatch(int $matchId): bool
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare(
                'SELECT wm.*,w.customer_id,w.id waitlist_id,c.name customer_name,c.phone,'
                . 's.name service_name,e.name establishment_name,u.name employee_name '
                . 'FROM waitlist_matches wm JOIN waitlist_entries w ON w.id=wm.waitlist_entry_id '
                . 'JOIN customers c ON c.id=w.customer_id JOIN services s ON s.id=w.service_id '
                . 'JOIN establishments e ON e.id=wm.establishment_id JOIN users u ON u.id=wm.employee_user_id '
                . 'WHERE wm.id=:id AND w.status="waiting" AND wm.status="available" LIMIT 1 FOR UPDATE'
            );
            $stmt->execute(['id' => $matchId]);
            $row = $stmt->fetch();
            if (!$row) {
                $pdo->rollBack();
                return false;
            }

            $settings = $this->settings((int) $row['establishment_id']);
            if ((int) $settings['whatsapp_enabled'] !== 1 || (int) $settings['waitlist_enabled'] !== 1) {
                $pdo->rollBack();
                return false;
            }

            $phone = $this->digits((string) ($row['phone'] ?? ''));
            if ($phone === '') {
                $pdo->rollBack();
                return false;
            }

            $offerMinutes = max(5, min(1440, (int) ($settings['waitlist_offer_minutes'] ?? 30)));
            $clock = new EstablishmentClock();
            $now = $clock->now($pdo, (int) $row['establishment_id']);
            $token = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);
            $expiresAt = $clock->sql($now->modify('+' . $offerMinutes . ' minutes'));
            $acceptUrl = url('/lista-espera/oferta/' . $token);

            $message = sprintf(
                'Oi %s! Surgiu um horário para %s em %s às %s com %s no %s. Você pode confirmar pelo link até %s; a vaga será validada no momento da confirmação: %s',
                $row['customer_name'],
                $row['service_name'],
                date('d/m/Y', strtotime((string) $row['slot_start'])),
                date('H:i', strtotime((string) $row['slot_start'])),
                $row['employee_name'],
                $row['establishment_name'],
                date('H:i', strtotime($expiresAt)),
                $acceptUrl
            );
            $dedupe = 'waitlist:' . $row['waitlist_id'] . ':' . $row['employee_user_id'] . ':'
                . date('YmdHi', strtotime((string) $row['slot_start'])) . ':' . substr($tokenHash, 0, 12);

            $queued = $this->queue(
                (int) $row['establishment_id'],
                (int) $row['customer_id'],
                null,
                (int) $row['waitlist_id'],
                'waitlist_slot_available',
                $phone,
                $message,
                $dedupe,
                $clock->sql($now)
            );
            if (!$queued) {
                $pdo->rollBack();
                return false;
            }

            $pdo->prepare(
                'UPDATE waitlist_matches SET status="queued",offer_token_hash=:token,offer_expires_at=:expires '
                . 'WHERE id=:id AND status="available"'
            )->execute([
                'token' => $tokenHash,
                'expires' => $expiresAt,
                'id' => $matchId,
            ]);

            $pdo->commit();
            return true;
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    private function queueAppointments(int $establishmentId, string $condition, string $event, array $conditionParams = []): int
    {
        $pdo = Database::connection();
        $clock = new EstablishmentClock();
        $scheduledAt = $clock->sql($clock->now($pdo, $establishmentId));
        $sql = 'SELECT a.id,a.customer_id,a.starts_at,e.name establishment_name,s.name service_name,p.name employee_name,COALESCE(c.name,u.name,"Cliente") customer_name,COALESCE(c.phone,u.phone) phone FROM appointments a JOIN establishments e ON e.id=a.establishment_id JOIN services s ON s.id=a.service_id JOIN users p ON p.id=a.employee_user_id LEFT JOIN customers c ON c.id=a.customer_id LEFT JOIN users u ON u.id=a.client_user_id WHERE a.establishment_id=:id AND ' . $condition;
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_merge(['id' => $establishmentId], $conditionParams));
        $count = 0;
        foreach ($stmt->fetchAll() as $row) {
            $phone = $this->digits((string) ($row['phone'] ?? ''));
            if ($phone === '') continue;
            $message = $this->message($event, $row);
            $slotKey = date('YmdHi', strtotime((string) $row['starts_at']));
            $dedupe = $event . ':' . $row['id'] . ($event === 'appointment_cancelled' ? '' : ':' . $slotKey);

            if ($event === 'appointment_cancelled') {
                $cancel = $pdo->prepare('UPDATE notification_outbox SET status="cancelled" WHERE appointment_id=:appointment AND status="pending" AND event_type IN ("appointment_confirmation","reminder_24h","reminder_2h")');
                $cancel->execute(['appointment' => $row['id']]);
            } else {
                $cancel = $pdo->prepare('UPDATE notification_outbox SET status="cancelled" WHERE appointment_id=:appointment AND event_type=:event AND status="pending" AND dedupe_key<>:dedupe');
                $cancel->execute(['appointment'=>$row['id'],'event'=>$event,'dedupe'=>$dedupe]);
            }

            if ($this->queue($establishmentId, $row['customer_id'] ? (int) $row['customer_id'] : null, (int) $row['id'], null, $event, $phone, $message, $dedupe, $scheduledAt)) $count++;
        }
        return $count;
    }

    private function message(string $event, array $row): string
    {
        $date = date('d/m/Y', strtotime((string) $row['starts_at']));
        $time = date('H:i', strtotime((string) $row['starts_at']));
        return match ($event) {
            'appointment_confirmation' => "Oi {$row['customer_name']}! Seu agendamento de {$row['service_name']} está confirmado para {$date} às {$time}, com {$row['employee_name']}, no {$row['establishment_name']}.",
            'appointment_cancelled' => "Oi {$row['customer_name']}. Seu agendamento de {$row['service_name']} de {$date} às {$time} no {$row['establishment_name']} foi cancelado.",
            'reminder_24h' => "Oi {$row['customer_name']}! Lembrete: amanhã você tem {$row['service_name']} às {$time}, com {$row['employee_name']}, no {$row['establishment_name']}.",
            'reminder_2h' => "Oi {$row['customer_name']}! Seu horário de {$row['service_name']} é hoje às {$time}, com {$row['employee_name']}, no {$row['establishment_name']}.",
            default => '',
        };
    }

    private function queue(int $establishmentId, ?int $customerId, ?int $appointmentId, ?int $waitlistId, string $event, string $recipient, string $message, string $dedupe, ?string $scheduledAt = null): bool
    {
        $pdo = Database::connection();
        if ($scheduledAt === null) {
            $clock = new EstablishmentClock();
            $scheduledAt = $clock->sql($clock->now($pdo, $establishmentId));
        }
        $stmt = $pdo->prepare('INSERT IGNORE INTO notification_outbox (establishment_id,customer_id,appointment_id,waitlist_entry_id,event_type,recipient,message,scheduled_at,dedupe_key) VALUES (:e,:c,:a,:w,:t,:r,:m,:scheduled,:d)');
        $stmt->execute(['e'=>$establishmentId,'c'=>$customerId,'a'=>$appointmentId,'w'=>$waitlistId,'t'=>$event,'r'=>$recipient,'m'=>$message,'scheduled'=>$scheduledAt,'d'=>$dedupe]);
        return $stmt->rowCount() > 0;
    }

    private function digits(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (strlen($digits) === 10 || strlen($digits) === 11) $digits = '55' . $digits;
        return $digits;
    }
}
