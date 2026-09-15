<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class NotificationService
{
    public function settings(int $establishmentId): array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM notification_settings WHERE establishment_id = :id LIMIT 1');
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
        ];
    }

    public function scheduleEstablishment(int $establishmentId): array
    {
        $settings = $this->settings($establishmentId);
        $result = ['confirmation' => 0, 'cancellation' => 0, 'reminder_24h' => 0, 'reminder_2h' => 0];
        if ((int) $settings['whatsapp_enabled'] !== 1) return $result;

        $rules = [
            'confirmation' => [(int) $settings['confirmation_enabled'], 'a.status="confirmed" AND a.starts_at>NOW() AND a.created_at>=DATE_SUB(NOW(), INTERVAL 7 DAY)', 'appointment_confirmation'],
            'cancellation' => [(int) $settings['cancellation_enabled'], 'a.status="cancelled" AND a.updated_at>=DATE_SUB(NOW(), INTERVAL 7 DAY)', 'appointment_cancelled'],
            'reminder_24h' => [(int) $settings['reminder_24h_enabled'], 'a.status="confirmed" AND a.starts_at BETWEEN DATE_ADD(NOW(), INTERVAL 23 HOUR) AND DATE_ADD(NOW(), INTERVAL 25 HOUR)', 'reminder_24h'],
            'reminder_2h' => [(int) $settings['reminder_2h_enabled'], 'a.status="confirmed" AND a.starts_at BETWEEN DATE_ADD(NOW(), INTERVAL 90 MINUTE) AND DATE_ADD(NOW(), INTERVAL 150 MINUTE)', 'reminder_2h'],
        ];
        foreach ($rules as $key => [$enabled, $condition, $event]) {
            if ($enabled === 1) $result[$key] = $this->queueAppointments($establishmentId, $condition, $event);
        }
        return $result;
    }

    public function queueWaitlistMatch(int $matchId): bool
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT wm.*, w.customer_id, w.id waitlist_id, c.name customer_name, c.phone, s.name service_name, e.name establishment_name, u.name employee_name FROM waitlist_matches wm JOIN waitlist_entries w ON w.id=wm.waitlist_entry_id JOIN customers c ON c.id=w.customer_id JOIN services s ON s.id=w.service_id JOIN establishments e ON e.id=wm.establishment_id JOIN users u ON u.id=wm.employee_user_id WHERE wm.id=:id AND w.status="waiting" LIMIT 1');
        $stmt->execute(['id' => $matchId]);
        $row = $stmt->fetch();
        if (!$row) return false;
        $settings = $this->settings((int) $row['establishment_id']);
        if ((int) $settings['whatsapp_enabled'] !== 1 || (int) $settings['waitlist_enabled'] !== 1) return false;
        $phone = $this->digits((string) ($row['phone'] ?? ''));
        if ($phone === '') return false;

        $message = sprintf('Oi %s! Surgiu um horário para %s em %s às %s com %s no %s. Entre em contato para confirmar.', $row['customer_name'], $row['service_name'], date('d/m/Y', strtotime((string) $row['slot_start'])), date('H:i', strtotime((string) $row['slot_start'])), $row['employee_name'], $row['establishment_name']);
        $dedupe = 'waitlist:' . $row['waitlist_id'] . ':' . $row['employee_user_id'] . ':' . date('YmdHi', strtotime((string) $row['slot_start']));
        $queued = $this->queue((int) $row['establishment_id'], (int) $row['customer_id'], null, (int) $row['waitlist_id'], 'waitlist_slot_available', $phone, $message, $dedupe);
        if ($queued) $pdo->prepare('UPDATE waitlist_matches SET status="queued" WHERE id=:id AND status="available"')->execute(['id' => $matchId]);
        return $queued;
    }

    private function queueAppointments(int $establishmentId, string $condition, string $event): int
    {
        $pdo = Database::connection();
        $sql = 'SELECT a.id,a.customer_id,a.starts_at,e.name establishment_name,s.name service_name,p.name employee_name,COALESCE(c.name,u.name,"Cliente") customer_name,COALESCE(c.phone,u.phone) phone FROM appointments a JOIN establishments e ON e.id=a.establishment_id JOIN services s ON s.id=a.service_id JOIN users p ON p.id=a.employee_user_id LEFT JOIN customers c ON c.id=a.customer_id LEFT JOIN users u ON u.id=a.client_user_id WHERE a.establishment_id=:id AND ' . $condition;
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $establishmentId]);
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

            if ($this->queue($establishmentId, $row['customer_id'] ? (int) $row['customer_id'] : null, (int) $row['id'], null, $event, $phone, $message, $dedupe)) $count++;
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

    private function queue(int $establishmentId, ?int $customerId, ?int $appointmentId, ?int $waitlistId, string $event, string $recipient, string $message, string $dedupe): bool
    {
        $stmt = Database::connection()->prepare('INSERT IGNORE INTO notification_outbox (establishment_id,customer_id,appointment_id,waitlist_entry_id,event_type,recipient,message,scheduled_at,dedupe_key) VALUES (:e,:c,:a,:w,:t,:r,:m,NOW(),:d)');
        $stmt->execute(['e'=>$establishmentId,'c'=>$customerId,'a'=>$appointmentId,'w'=>$waitlistId,'t'=>$event,'r'=>$recipient,'m'=>$message,'d'=>$dedupe]);
        return $stmt->rowCount() > 0;
    }

    private function digits(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (strlen($digits) === 10 || strlen($digits) === 11) $digits = '55' . $digits;
        return $digits;
    }
}
