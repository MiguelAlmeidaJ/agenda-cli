<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;

final class AvailabilityService
{
    public function slots(int $establishmentId, int $serviceId, int $employeeId, string $date): array
    {
        $pdo = Database::connection();

        $serviceStmt = $pdo->prepare(
            'SELECT s.duration_minutes, e.timezone '
            . 'FROM services s JOIN establishments e ON e.id = s.establishment_id '
            . 'WHERE s.id = :service AND s.establishment_id = :establishment AND s.active = 1 LIMIT 1'
        );
        $serviceStmt->execute(['service' => $serviceId, 'establishment' => $establishmentId]);
        $service = $serviceStmt->fetch();
        if (!$service) {
            return [];
        }

        $employeeStmt = $pdo->prepare(
            'SELECT 1 FROM establishment_users eu '
            . 'JOIN employee_services es ON es.employee_user_id = eu.user_id '
            . 'WHERE eu.establishment_id = :establishment AND eu.user_id = :employee '
            . 'AND eu.active = 1 AND es.service_id = :service LIMIT 1'
        );
        $employeeStmt->execute([
            'establishment' => $establishmentId,
            'employee' => $employeeId,
            'service' => $serviceId,
        ]);
        if (!$employeeStmt->fetchColumn()) {
            return [];
        }

        try {
            $timezone = new DateTimeZone((string) $service['timezone']);
            $day = new DateTimeImmutable($date . ' 00:00:00', $timezone);
        } catch (\Throwable) {
            return [];
        }

        if ($day->format('Y-m-d') !== $date) {
            return [];
        }

        $hoursStmt = $pdo->prepare(
            'SELECT opens_at, closes_at, is_closed FROM business_hours '
            . 'WHERE establishment_id = :establishment AND weekday = :weekday LIMIT 1'
        );
        $hoursStmt->execute([
            'establishment' => $establishmentId,
            'weekday' => (int) $day->format('N'),
        ]);
        $hours = $hoursStmt->fetch();
        if (!$hours || (int) $hours['is_closed'] === 1 || !$hours['opens_at'] || !$hours['closes_at']) {
            return [];
        }

        $opening = new DateTimeImmutable($date . ' ' . $hours['opens_at'], $timezone);
        $closing = new DateTimeImmutable($date . ' ' . $hours['closes_at'], $timezone);
        $duration = new DateInterval('PT' . (int) $service['duration_minutes'] . 'M');
        $step = new DateInterval('PT15M');

        $busyStmt = $pdo->prepare(
            'SELECT starts_at, ends_at FROM appointments '
            . 'WHERE establishment_id = :establishment AND employee_user_id = :employee '
            . 'AND status IN ("pending", "confirmed") AND starts_at < :day_end AND ends_at > :day_start'
        );
        $busyStmt->execute([
            'establishment' => $establishmentId,
            'employee' => $employeeId,
            'day_start' => $day->format('Y-m-d H:i:s'),
            'day_end' => $day->modify('+1 day')->format('Y-m-d H:i:s'),
        ]);
        $busy = $busyStmt->fetchAll();

        $blockedStmt = $pdo->prepare(
            'SELECT starts_at, ends_at FROM blocked_periods '
            . 'WHERE establishment_id = :establishment '
            . 'AND (employee_user_id IS NULL OR employee_user_id = :employee) '
            . 'AND starts_at < :day_end AND ends_at > :day_start'
        );
        $blockedStmt->execute([
            'establishment' => $establishmentId,
            'employee' => $employeeId,
            'day_start' => $day->format('Y-m-d H:i:s'),
            'day_end' => $day->modify('+1 day')->format('Y-m-d H:i:s'),
        ]);
        $blocked = $blockedStmt->fetchAll();

        $now = new DateTimeImmutable('now', $timezone);
        $slots = [];

        for ($cursor = $opening; $cursor->add($duration) <= $closing; $cursor = $cursor->add($step)) {
            $end = $cursor->add($duration);
            if ($cursor <= $now) {
                continue;
            }
            if ($this->overlaps($cursor, $end, $busy, $timezone) || $this->overlaps($cursor, $end, $blocked, $timezone)) {
                continue;
            }
            $slots[] = $cursor->format('H:i');
        }

        return $slots;
    }

    private function overlaps(DateTimeImmutable $start, DateTimeImmutable $end, array $periods, DateTimeZone $timezone): bool
    {
        foreach ($periods as $period) {
            $periodStart = new DateTimeImmutable((string) $period['starts_at'], $timezone);
            $periodEnd = new DateTimeImmutable((string) $period['ends_at'], $timezone);
            if ($start < $periodEnd && $end > $periodStart) {
                return true;
            }
        }
        return false;
    }
}
