<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;
use DateTimeZone;
use PDO;

final class EstablishmentClock
{
    public function now(PDO $pdo, int $establishmentId): DateTimeImmutable
    {
        return new DateTimeImmutable('now', $this->timezone($pdo, $establishmentId));
    }

    public function timezone(PDO $pdo, int $establishmentId): DateTimeZone
    {
        $stmt = $pdo->prepare('SELECT timezone FROM establishments WHERE id=:id LIMIT 1');
        $stmt->execute(['id' => $establishmentId]);
        $name = trim((string) ($stmt->fetchColumn() ?: env('APP_TIMEZONE', 'America/Sao_Paulo')));

        try {
            return new DateTimeZone($name);
        } catch (\Throwable) {
            return new DateTimeZone((string) env('APP_TIMEZONE', 'America/Sao_Paulo'));
        }
    }

    public function inTimezone(string $timezone, string $time = 'now'): DateTimeImmutable
    {
        try {
            return new DateTimeImmutable($time, new DateTimeZone($timezone));
        } catch (\Throwable) {
            return new DateTimeImmutable($time, new DateTimeZone((string) env('APP_TIMEZONE', 'America/Sao_Paulo')));
        }
    }

    public function sql(DateTimeImmutable $dateTime): string
    {
        return $dateTime->format('Y-m-d H:i:s');
    }

    public function dayBounds(DateTimeImmutable $reference): array
    {
        $start = $reference->setTime(0, 0, 0);
        return [$start, $start->modify('+1 day')];
    }

    public function monthBounds(DateTimeImmutable $reference): array
    {
        $start = $reference->modify('first day of this month')->setTime(0, 0, 0);
        return [$start, $start->modify('+1 month')];
    }

    public function previousMonthBounds(DateTimeImmutable $reference): array
    {
        [$currentStart] = $this->monthBounds($reference);
        $previousStart = $currentStart->modify('-1 month');
        return [$previousStart, $currentStart];
    }
}
