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
            'SELECT s.duration_minutes, e.timezone '
            . 'FROM services s JOIN establishments e ON e.id = s.establishment_id '
            . 'WHERE s.id = :service AND s.establishment_id = :establishment AND s.active = 1 LIMIT 1'
        );
        $serviceStmt->execute(['service' => $serviceId, 'establishment' => $establishmentId]);
        $service = $serviceStmt->fetch();
        if (!$service) {
            return [];
        }

        $providerStmt = $pdo->prepare(
            'SELECT 1 FROM employee_services es '
            . 'JOIN services s ON s.id = es.service_id '
            . 'JOIN establishments e ON e.id = s.establishment_id '
            . 'JOIN users u ON u.id = es.employee_user_id '
            . 'LEFT JOIN establishment_users eu ON eu.establishment_id = e.id AND eu.user_id = u.id '
            . 'WHERE e.id = :establishment AND s.id = :service AND es.employee_user_id = :employee '
            . 'AND u.status = "active" '
            . 'AND (u.id = e.owner_user_id OR (eu.role = "employee" AND eu.active = 1)) LIMIT 1'
        );
        $providerStmt->execute([
            'establishment' => $establishmentId,
            'employee' => $employeeId,
            'service' => $serviceId,
        ]);
        if (!$providerStmt->fetchColumn()) {
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

        $hours = $this->hoursForDate($pdo, $establishmentId, $day);
        if ($hours === null) {
            return [];
        }

        $hours = $this->applyProviderHours($pdo, $establishmentId, $employeeId, $day, $hours);
        if ($hours === null) {
            return [];
        }

        $opening = new DateTimeImmutable($date . ' ' . $hours['opens_at'], $timezone);
        $closing = new DateTimeImmutable($date . ' ' . $hours['closes_at'], $timezone);
        $duration = new DateInterval('PT' . (int) $service['duration_minutes'] . 'M');
        $step = new DateInterval('PT15M');

        $busySql = 'SELECT starts_at, ends_at FROM appointments '
            . 'WHERE establishment_id = :establishment AND employee_user_id = :employee '
            . 'AND status IN ("pending", "confirmed") AND starts_at < :day_end AND ends_at > :day_start';
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

    /**
     * Retorna um único profissional para cada horário livre do serviço.
     * Quando mais de um profissional está livre no mesmo horário, usa o primeiro
     * da ordenação estável por nome/id. O cliente continua recebendo um horário
     * real e o provider escolhido é revalidado transacionalmente ao confirmar.
     */
    public function slotsForAnyProvider(int $establishmentId, int $serviceId, string $date): array
    {
        $pdo = Database::connection();
        $providers = $pdo->prepare(
            'SELECT u.id, u.name FROM employee_services es '
            . 'JOIN services s ON s.id = es.service_id '
            . 'JOIN establishments e ON e.id = s.establishment_id '
            . 'JOIN users u ON u.id = es.employee_user_id '
            . 'LEFT JOIN establishment_users eu ON eu.establishment_id = e.id AND eu.user_id = u.id '
            . 'WHERE e.id = :establishment AND s.id = :service AND s.active = 1 AND u.status = "active" '
            . 'AND (u.id = e.owner_user_id OR (eu.role = "employee" AND eu.active = 1)) '
            . 'ORDER BY u.name, u.id'
        );
        $providers->execute([
            'establishment' => $establishmentId,
            'service' => $serviceId,
        ]);

        $byTime = [];
        foreach ($providers->fetchAll() as $provider) {
            $providerId = (int) $provider['id'];
            foreach ($this->slots($establishmentId, $serviceId, $providerId, $date) as $time) {
                if (isset($byTime[$time])) {
                    continue;
                }
                $byTime[$time] = [
                    'time' => $time,
                    'employee_id' => $providerId,
                    'employee_name' => (string) $provider['name'],
                ];
            }
        }

        ksort($byTime, SORT_STRING);
        return array_values($byTime);
    }

    private function hoursForDate(\PDO $pdo, int $establishmentId, DateTimeImmutable $day): ?array
    {
        $date = $day->format('Y-m-d');
        $specialStmt = $pdo->prepare(
            'SELECT opens_at, closes_at, is_closed FROM special_hours '
            . 'WHERE establishment_id = :establishment AND special_date = :date LIMIT 1'
        );
        $specialStmt->execute([
            'establishment' => $establishmentId,
            'date' => $date,
        ]);
        $special = $specialStmt->fetch();

        if ($special) {
            if ((int) $special['is_closed'] === 1 || !$special['opens_at'] || !$special['closes_at']) {
                return null;
            }
            return $special;
        }

        if ((new BrazilHolidayService())->nameForDate($date) !== null) {
            return null;
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
            return null;
        }

        return $hours;
    }

    private function applyProviderHours(\PDO $pdo, int $establishmentId, int $providerId, DateTimeImmutable $day, array $establishmentHours): ?array
    {
        $stmt = $pdo->prepare(
            'SELECT opens_at, closes_at, is_off FROM provider_hours '
            . 'WHERE establishment_id = :establishment AND user_id = :provider AND weekday = :weekday LIMIT 1'
        );
        $stmt->execute([
            'establishment' => $establishmentId,
            'provider' => $providerId,
            'weekday' => (int) $day->format('N'),
        ]);
        $providerHours = $stmt->fetch();

        if (!$providerHours) {
            return $establishmentHours;
        }

        if ((int) $providerHours['is_off'] === 1 || !$providerHours['opens_at'] || !$providerHours['closes_at']) {
            return null;
        }

        $opensAt = max(substr((string) $establishmentHours['opens_at'], 0, 8), substr((string) $providerHours['opens_at'], 0, 8));
        $closesAt = min(substr((string) $establishmentHours['closes_at'], 0, 8), substr((string) $providerHours['closes_at'], 0, 8));

        if ($opensAt >= $closesAt) {
            return null;
        }

        return [
            'opens_at' => $opensAt,
            'closes_at' => $closesAt,
            'is_closed' => 0,
        ];
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
