<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;
use DateTimeZone;

final class AppointmentRecurrenceService
{
    public function dates(string $startDate, int $intervalWeeks, int $occurrences, string $timezone): array
    {
        if ($intervalWeeks < 1 || $intervalWeeks > 4) {
            throw new \RuntimeException('A recorrência deve repetir a cada 1 a 4 semanas.');
        }
        if ($occurrences < 2 || $occurrences > 52) {
            throw new \RuntimeException('A recorrência deve ter entre 2 e 52 atendimentos.');
        }

        try {
            $zone = new DateTimeZone($timezone);
            $start = new DateTimeImmutable($startDate . ' 00:00:00', $zone);
        } catch (\Throwable) {
            throw new \RuntimeException('Data inicial inválida para a recorrência.');
        }

        if ($start->format('Y-m-d') !== $startDate) {
            throw new \RuntimeException('Data inicial inválida para a recorrência.');
        }

        $dates = [];
        for ($position = 0; $position < $occurrences; $position++) {
            $dates[] = $start->modify('+' . ($position * $intervalWeeks) . ' weeks')->format('Y-m-d');
        }

        return $dates;
    }
}
