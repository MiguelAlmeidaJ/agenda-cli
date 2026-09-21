<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;
use DateTimeZone;

final class BrazilHolidayService
{
    public function forYear(int $year): array
    {
        $easter = $this->easterSunday($year);

        $holidays = [
            sprintf('%04d-01-01', $year) => 'Confraternização Universal',
            $easter->modify('-48 days')->format('Y-m-d') => 'Carnaval (segunda-feira, ponto facultativo)',
            $easter->modify('-47 days')->format('Y-m-d') => 'Carnaval (terça-feira, ponto facultativo)',
            $easter->modify('-2 days')->format('Y-m-d') => 'Paixão de Cristo',
            sprintf('%04d-04-21', $year) => 'Tiradentes',
            sprintf('%04d-05-01', $year) => 'Dia Mundial do Trabalho',
            $easter->modify('+60 days')->format('Y-m-d') => 'Corpus Christi (ponto facultativo)',
            sprintf('%04d-09-07', $year) => 'Independência do Brasil',
            sprintf('%04d-10-12', $year) => 'Nossa Senhora Aparecida',
            sprintf('%04d-11-02', $year) => 'Finados',
            sprintf('%04d-11-15', $year) => 'Proclamação da República',
            sprintf('%04d-11-20', $year) => 'Dia Nacional de Zumbi e da Consciência Negra',
            sprintf('%04d-12-25', $year) => 'Natal',
        ];

        ksort($holidays);
        return $holidays;
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

    private function easterSunday(int $year): DateTimeImmutable
    {
        // Algoritmo gregoriano de Meeus/Jones/Butcher.
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;

        return new DateTimeImmutable(
            sprintf('%04d-%02d-%02d 00:00:00', $year, $month, $day),
            new DateTimeZone('America/Sao_Paulo')
        );
    }
}
