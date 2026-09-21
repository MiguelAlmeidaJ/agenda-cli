<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;

final class AvailabilityService
{
    public function slots(int $establishmentId, int $serviceId, int $employeeId, string $date, ?int $excludeAppointmentId = null): array
    {
        $pdo = Database::connection();

        $serviceStmt = $pdo->prepare(
            'SELECT s.duration_minutes, e.timezone FROM services s '
            . 'JOIN establishments e ON e.id = s.establishment_id '
            . 'WHERE s.id = :service AND s.establishment_id = :establishment AND s.active = 1 LIMIT 1'
        );
        $serviceStmt->execute(['service' => $serviceId, 'establishment' => $establishmentId]);
        $service = $serviceStmt->fetch();
        if (!$service) return [];

        $providerStmt = $pdo->prepare(
            'SELECT 1 FROM employee_services es JOIN services s ON s.id = es.service_id '
            . 'JOIN establishments e ON e.id = s.establishment_id JOIN users u ON u.id = es.employee_user_id '
            . 'LEFT JOIN establishment_users eu ON eu.establishment_id = e.id AND eu.user_id = u.id '
            . 'WHERE e.id = :establishment AND s.id = :service AND es.employee_user_id = :employee '
            . 'AND u.status = "active" AND (u.id = e.owner_user_id OR (eu.role = "employee" AND eu.active = 1)) LIMIT 1'
        );
        $providerStmt->execute(['establishment' => $establishmentId, 'employee' => $employeeId, 'service' => $serviceId]);
        if (!$providerStmt->fetchColumn()) return [];

        try {
            $timezone = new DateTimeZone((string) $service['timezone']);
            $day = new DateTimeImmutable($date . ' 00:00:00', $timezone);
        } catch (\Throwable) {
            return [];
        }
        if ($day->format('Y-m-d') !== $date) return [];

        $settings = $this->settings($pdo, $establishmentId);
        $now = new DateTimeImmutable('now', $timezone);
        if ($day > $now->modify('+' . (int) $settings['max_advance_days'] . ' days')->setTime(23, 59, 59)) return [];

        $ranges = $this->rangesForDate($pdo, $establishmentId, $day);
        if ($ranges === []) return [];
        $ranges = $this->applyProviderRanges($pdo, $establishmentId, $employeeId, $day, $ranges);
        if ($ranges === []) return [];

        $duration = new DateInterval('PT' . (int) $service['duration_minutes'] . 'M');
        $step = new DateInterval('PT15M');

        $busySql = 'SELECT starts_at, ends_at FROM appointments WHERE establishment_id = :establishment '
            . 'AND employee_user_id = :employee AND status IN ("pending", "confirmed") '
            . 'AND starts_at < :day_end AND ends_at > :day_start';
        $busyParams = [
            'establishment' => $establishmentId,
            'employee' => $employeeId,
            'day_start' => $day->format('Y-m-d H:i:s'),
            'day_end' => $day->modify('+1 day')->format('Y-m-d H:i:s'),
        ];
        if ($excludeAppointmentId !== null) {
            $busySql .= ' AND id <> :exclude';
            $busyParams['exclude'] = $excludeAppointmentId;
        }
        $busyStmt = $pdo->prepare($busySql);
        $busyStmt->execute($busyParams);
        $busy = $busyStmt->fetchAll();

        $blockedStmt = $pdo->prepare(
            'SELECT starts_at, ends_at FROM blocked_periods WHERE establishment_id = :establishment '
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

        try {
            $absenceStmt = $pdo->prepare(
                'SELECT kind,starts_on,ends_on,weekday,all_day,starts_at,ends_at FROM provider_absences '
                . 'WHERE establishment_id=:establishment AND user_id=:employee '
                . 'AND starts_on<=:date_start AND (ends_on IS NULL OR ends_on>=:date_end) '
                . 'AND (kind="date_range" OR (kind="weekly" AND weekday=:weekday))'
            );
            $absenceStmt->execute([
                'establishment' => $establishmentId,
                'employee' => $employeeId,
                'date_start' => $date,
                'date_end' => $date,
                'weekday' => (int) $day->format('N'),
            ]);
            $blocked = array_merge(
                $blocked,
                (new ProviderAbsenceService())->periodsForDate($absenceStmt->fetchAll(), $day)
            );
        } catch (\PDOException) {
            // Compatibilidade durante deploy antes da migration de ausências.
        }

        $minimumStart = $now->modify('+' . (int) $settings['min_notice_minutes'] . ' minutes');
        $bufferMinutes = (int) $settings['buffer_minutes'];
        $slots = [];

        foreach ($ranges as $range) {
            $opening = new DateTimeImmutable($date . ' ' . $range['opens_at'], $timezone);
            $closing = new DateTimeImmutable($date . ' ' . $range['closes_at'], $timezone);

            for ($cursor = $opening; $cursor->add($duration) <= $closing; $cursor = $cursor->add($step)) {
                $end = $cursor->add($duration);
                if ($cursor < $minimumStart) continue;
                if ($this->overlaps($cursor, $end, $blocked, $timezone)) continue;
                if ($this->overlapsWithBuffer($cursor, $end, $busy, $timezone, $bufferMinutes)) continue;
                $slots[$cursor->format('H:i')] = true;
            }
        }

        $times = array_keys($slots);
        sort($times, SORT_STRING);
        return $times;
    }

    public function slotsForAnyProvider(int $establishmentId, int $serviceId, string $date): array
    {
        $pdo = Database::connection();
        $providers = $pdo->prepare(
            'SELECT u.id, u.name FROM employee_services es JOIN services s ON s.id = es.service_id '
            . 'JOIN establishments e ON e.id = s.establishment_id JOIN users u ON u.id = es.employee_user_id '
            . 'LEFT JOIN establishment_users eu ON eu.establishment_id = e.id AND eu.user_id = u.id '
            . 'WHERE e.id = :establishment AND s.id = :service AND s.active = 1 AND u.status = "active" '
            . 'AND (u.id = e.owner_user_id OR (eu.role = "employee" AND eu.active = 1)) ORDER BY u.name, u.id'
        );
        $providers->execute(['establishment' => $establishmentId, 'service' => $serviceId]);
        $byTime = [];
        foreach ($providers->fetchAll() as $provider) {
            $providerId = (int) $provider['id'];
            foreach ($this->slots($establishmentId, $serviceId, $providerId, $date) as $time) {
                if (!isset($byTime[$time])) {
                    $byTime[$time] = ['time' => $time, 'employee_id' => $providerId, 'employee_name' => (string) $provider['name']];
                }
            }
        }
        ksort($byTime, SORT_STRING);
        return array_values($byTime);
    }

    public function settingsForEstablishment(int $establishmentId): array
    {
        return $this->settings(Database::connection(), $establishmentId);
    }

    private function settings(\PDO $pdo, int $establishmentId): array
    {
        $stmt = $pdo->prepare('SELECT * FROM booking_settings WHERE establishment_id = :establishment LIMIT 1');
        $stmt->execute(['establishment' => $establishmentId]);
        return $stmt->fetch() ?: [
            'min_notice_minutes' => 0,
            'max_advance_days' => 90,
            'buffer_minutes' => 0,
            'cancellation_notice_minutes' => 0,
            'allow_waitlist' => 1,
            'cancellation_policy' => null,
        ];
    }

    private function rangesForDate(\PDO $pdo, int $establishmentId, DateTimeImmutable $day): array
    {
        $date = $day->format('Y-m-d');
        $specialStmt = $pdo->prepare('SELECT opens_at, closes_at, is_closed FROM special_hours WHERE establishment_id = :establishment AND special_date = :date LIMIT 1');
        $specialStmt->execute(['establishment' => $establishmentId, 'date' => $date]);
        $special = $specialStmt->fetch();
        if ($special) {
            if ((int) $special['is_closed'] === 1 || !$special['opens_at'] || !$special['closes_at']) return [];
            return [[
                'opens_at' => substr((string) $special['opens_at'], 0, 5),
                'closes_at' => substr((string) $special['closes_at'], 0, 5),
            ]];
        }
        if ((new BrazilHolidayService())->nameForDate($date) !== null) return [];

        $weekday = (int) $day->format('N');
        $stmt = $pdo->prepare('SELECT opens_at, closes_at, is_closed FROM business_hours WHERE establishment_id = :establishment AND weekday = :weekday LIMIT 1');
        $stmt->execute(['establishment' => $establishmentId, 'weekday' => $weekday]);
        $hours = $stmt->fetch();
        if (!$hours || (int) $hours['is_closed'] === 1) return [];

        try {
            $rangeStmt = $pdo->prepare(
                'SELECT opens_at,closes_at FROM business_hour_ranges '
                . 'WHERE establishment_id=:establishment AND weekday=:weekday ORDER BY sort_order,id'
            );
            $rangeStmt->execute(['establishment' => $establishmentId, 'weekday' => $weekday]);
            $ranges = $rangeStmt->fetchAll();
            if ($ranges !== []) {
                return array_map(static fn (array $range): array => [
                    'opens_at' => substr((string) $range['opens_at'], 0, 5),
                    'closes_at' => substr((string) $range['closes_at'], 0, 5),
                ], $ranges);
            }
        } catch (\PDOException) {
            // Compatibilidade durante deploy antes da migration de múltiplas faixas.
        }

        if (!$hours['opens_at'] || !$hours['closes_at']) return [];
        return [[
            'opens_at' => substr((string) $hours['opens_at'], 0, 5),
            'closes_at' => substr((string) $hours['closes_at'], 0, 5),
        ]];
    }

    private function applyProviderRanges(\PDO $pdo, int $establishmentId, int $providerId, DateTimeImmutable $day, array $establishmentRanges): array
    {
        $weekday = (int) $day->format('N');
        $stmt = $pdo->prepare('SELECT opens_at, closes_at, is_off FROM provider_hours WHERE establishment_id = :establishment AND user_id = :provider AND weekday = :weekday LIMIT 1');
        $stmt->execute(['establishment' => $establishmentId, 'provider' => $providerId, 'weekday' => $weekday]);
        $providerHours = $stmt->fetch();

        if (!$providerHours) return $establishmentRanges;
        if ((int) $providerHours['is_off'] === 1) return [];

        $providerRanges = [];
        try {
            $rangeStmt = $pdo->prepare(
                'SELECT opens_at,closes_at FROM provider_hour_ranges '
                . 'WHERE establishment_id=:establishment AND user_id=:provider AND weekday=:weekday ORDER BY sort_order,id'
            );
            $rangeStmt->execute([
                'establishment' => $establishmentId,
                'provider' => $providerId,
                'weekday' => $weekday,
            ]);
            $providerRanges = array_map(static fn (array $range): array => [
                'opens_at' => substr((string) $range['opens_at'], 0, 5),
                'closes_at' => substr((string) $range['closes_at'], 0, 5),
            ], $rangeStmt->fetchAll());
        } catch (\PDOException) {
            // Compatibilidade durante deploy antes da migration de múltiplas faixas.
        }

        if ($providerRanges === [] && $providerHours['opens_at'] && $providerHours['closes_at']) {
            $providerRanges[] = [
                'opens_at' => substr((string) $providerHours['opens_at'], 0, 5),
                'closes_at' => substr((string) $providerHours['closes_at'], 0, 5),
            ];
        }

        if ($providerRanges === []) return [];
        return (new ScheduleRangeService())->intersect($establishmentRanges, $providerRanges);
    }

    private function overlaps(DateTimeImmutable $start, DateTimeImmutable $end, array $periods, DateTimeZone $timezone): bool
    {
        foreach ($periods as $period) {
            $periodStart = new DateTimeImmutable((string) $period['starts_at'], $timezone);
            $periodEnd = new DateTimeImmutable((string) $period['ends_at'], $timezone);
            if ($start < $periodEnd && $end > $periodStart) return true;
        }
        return false;
    }

    private function overlapsWithBuffer(DateTimeImmutable $start, DateTimeImmutable $end, array $periods, DateTimeZone $timezone, int $bufferMinutes): bool
    {
        foreach ($periods as $period) {
            $periodStart = new DateTimeImmutable((string) $period['starts_at'], $timezone);
            $periodEnd = new DateTimeImmutable((string) $period['ends_at'], $timezone);
            if ($bufferMinutes > 0) {
                $periodStart = $periodStart->modify('-' . $bufferMinutes . ' minutes');
                $periodEnd = $periodEnd->modify('+' . $bufferMinutes . ' minutes');
            }
            if ($start < $periodEnd && $end > $periodStart) return true;
        }
        return false;
    }
}
