<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class WaitlistAutomationService
{
    public function scanEstablishment(int $establishmentId, int $limit = 200): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT w.id,w.service_id,w.preferred_employee_user_id,w.desired_date,w.time_period '
            . 'FROM waitlist_entries w JOIN services s ON s.id=w.service_id '
            . 'WHERE w.establishment_id=:establishment AND w.status="waiting" '
            . 'AND w.desired_date>=CURDATE() AND s.active=1 ORDER BY w.desired_date,w.created_at LIMIT ' . max(1, min(500, $limit))
        );
        $stmt->execute(['establishment' => $establishmentId]);

        $availability = new AvailabilityService();
        $notifications = new NotificationService();
        $matched = 0;
        $queued = 0;

        foreach ($stmt->fetchAll() as $entry) {
            $candidate = $this->findSlot($availability, $establishmentId, $entry);
            if ($candidate === null) {
                $this->expireEntryMatches($pdo, (int) $entry['id']);
                continue;
            }

            $slotStart = $entry['desired_date'] . ' ' . $candidate['time'] . ':00';
            $dedupe = 'waitlist:' . $entry['id'] . ':' . $candidate['employee_id'] . ':' . date('YmdHi', strtotime($slotStart));
            $expire = $pdo->prepare('UPDATE waitlist_matches SET status="expired" WHERE waitlist_entry_id=:entry AND status IN ("available","queued") AND NOT (employee_user_id=:employee AND slot_start=:slot)');
            $expire->execute(['entry'=>$entry['id'],'employee'=>$candidate['employee_id'],'slot'=>$slotStart]);
            $cancel = $pdo->prepare('UPDATE notification_outbox SET status="cancelled" WHERE waitlist_entry_id=:entry AND event_type="waitlist_slot_available" AND status="pending" AND dedupe_key<>:dedupe');
            $cancel->execute(['entry'=>$entry['id'],'dedupe'=>$dedupe]);

            $insert = $pdo->prepare(
                'INSERT INTO waitlist_matches (establishment_id,waitlist_entry_id,employee_user_id,slot_start,status) '
                . 'VALUES (:establishment,:entry,:employee,:slot,"available") '
                . 'ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id), status=IF(status="expired","available",status), updated_at=CURRENT_TIMESTAMP'
            );
            $insert->execute(['establishment'=>$establishmentId,'entry'=>$entry['id'],'employee'=>$candidate['employee_id'],'slot'=>$slotStart]);
            $matchId = (int) $pdo->lastInsertId();
            if ($matchId <= 0) {
                $find = $pdo->prepare('SELECT id FROM waitlist_matches WHERE waitlist_entry_id=:entry AND employee_user_id=:employee AND slot_start=:slot LIMIT 1');
                $find->execute(['entry'=>$entry['id'],'employee'=>$candidate['employee_id'],'slot'=>$slotStart]);
                $matchId = (int) ($find->fetchColumn() ?: 0);
            }
            if ($matchId <= 0) continue;

            $matched++;
            if ($notifications->queueWaitlistMatch($matchId)) $queued++;
        }

        $pdo->prepare('UPDATE waitlist_matches SET status="expired" WHERE establishment_id=:establishment AND status IN ("available","queued") AND slot_start<NOW()')->execute(['establishment'=>$establishmentId]);
        return ['matched' => $matched, 'queued' => $queued];
    }

    private function expireEntryMatches(\PDO $pdo, int $entryId): void
    {
        $pdo->prepare('UPDATE waitlist_matches SET status="expired" WHERE waitlist_entry_id=:entry AND status IN ("available","queued")')->execute(['entry'=>$entryId]);
        $pdo->prepare('UPDATE notification_outbox SET status="cancelled" WHERE waitlist_entry_id=:entry AND event_type="waitlist_slot_available" AND status="pending"')->execute(['entry'=>$entryId]);
    }

    private function findSlot(AvailabilityService $availability, int $establishmentId, array $entry): ?array
    {
        $providerId = (int) ($entry['preferred_employee_user_id'] ?? 0);
        if ($providerId > 0) {
            foreach ($availability->slots($establishmentId, (int) $entry['service_id'], $providerId, (string) $entry['desired_date']) as $time) {
                if ($this->periodMatches($time, (string) $entry['time_period'])) return ['time'=>$time,'employee_id'=>$providerId];
            }
            return null;
        }

        foreach ($availability->slotsForAnyProvider($establishmentId, (int) $entry['service_id'], (string) $entry['desired_date']) as $slot) {
            if ($this->periodMatches((string) $slot['time'], (string) $entry['time_period'])) return ['time'=>(string)$slot['time'],'employee_id'=>(int)$slot['employee_id']];
        }
        return null;
    }

    private function periodMatches(string $time, string $period): bool
    {
        if ($period === 'any') return true;
        $hour = (int) substr($time, 0, 2);
        return match ($period) {
            'morning' => $hour < 12,
            'afternoon' => $hour >= 12 && $hour < 18,
            'evening' => $hour >= 18,
            default => true,
        };
    }
}
