<?php

declare(strict_types=1);

namespace App\Services;

final class BrazilHolidayService
{
    public function forYear(int $year): array
    {
        return [
            sprintf('%04d-01-01', $year) => 'Confraternização Universal',
            sprintf('%04d-04-21', $year) => 'Tiradentes',
            sprintf('%04d-05-01', $year) => 'Dia Mundial do Trabalho',
            sprintf('%04d-09-07', $year) => 'Independência do Brasil',
            sprintf('%04d-10-12', $year) => 'Nossa Senhora Aparecida',
            sprintf('%04d-11-02', $year) => 'Finados',
            sprintf('%04d-11-15', $year) => 'Proclamação da República',
            sprintf('%04d-11-20', $year) => 'Dia Nacional de Zumbi e da Consciência Negra',
            sprintf('%04d-12-25', $year) => 'Natal',
        ];
    }

    public function betweenYears(int $startYear, int $endYear): array
    {
        $holidays = [];
        for ($year = $startYear; $year <= $endYear; $year++) {
            $holidays += $this->forYear($year);
        }
        ksort($holidays);
        return $holidays;
    }

    public function nameForDate(string $date): ?string
    {
        $year = (int) substr($date, 0, 4);
        if ($year < 1) {
            return null;
        }
        return $this->forYear($year)[$date] ?? null;
    }
}
