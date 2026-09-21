<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;
use DateTimeZone;

final class ProviderAbsenceService
{
    public function normalizeDateRange(string $startsOn, string $endsOn, string $reason = ''): array
    {
        $start = $this->date($startsOn);
        $end = $this->date($endsOn);
        if ($end < $start) {
            throw new \RuntimeException('A data final das férias/ausência deve ser igual ou posterior à inicial.');
        }

        return [
            'kind' => 'date_range',
            'starts_on' => $start->format('Y-m-d'),
            'ends_on' => $end->format('Y-m-d'),
            'weekday' => null,
            'all_day' => 1,
            'starts_at' => null,
            'ends_at' => null,
            'reason' => $this->reason($reason),
        ];
    }

    public function normalizeWeekly(
        int $weekday,
        string $startsOn,
        ?string $endsOn,
        string $startsAt,
        string $endsAt,
        string $reason = ''
    ): array {
        if ($weekday < 1 || $weekday > 7) {
            throw new \RuntimeException('Selecione um dia da semana válido.');
        }

        $start = $this->date($startsOn);
        $end = $endsOn !== null && trim($endsOn) !== '' ? $this->date($endsOn) : null;
        if ($end !== null && $end < $start) {
            throw new \RuntimeException('A vigência final não pode ser anterior à inicial.');
        }

        $startsAt = trim($startsAt);
        $endsAt = trim($endsAt);
        if (!$this->validTime($startsAt) || !$this->validTime($endsAt) || $startsAt >= $endsAt) {
            throw new \RuntimeException('Informe um horário recorrente válido.');
        }

        return [
            'kind' => 'weekly',
            'starts_on' => $start->format('Y-m-d'),
            'ends_on' => $end?->format('Y-m-d'),
            'weekday' => $weekday,
            'all_day' => 0,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'reason' => $this->reason($reason),
        ];
    }

    public function periodsForDate(array $rules, DateTimeImmutable $day): array
    {
        $date = $day->format('Y-m-d');
        $weekday = (int) $day->format('N');
        $timezone = $day->getTimezone();
        $periods = [];

        foreach ($rules as $rule) {
            $startsOn = (string) ($rule['starts_on'] ?? '');
            $endsOn = (string) ($rule['ends_on'] ?? '');
            if ($startsOn === '' || $date < $startsOn || ($endsOn !== '' && $date > $endsOn)) {
                continue;
            }

            $kind = (string) ($rule['kind'] ?? '');
            if ($kind === 'weekly' && (int) ($rule['weekday'] ?? 0) !== $weekday) {
                continue;
            }
            if (!in_array($kind, ['date_range', 'weekly'], true)) {
                continue;
            }

            if ((int) ($rule['all_day'] ?? 0) === 1) {
                $start = new DateTimeImmutable($date . ' 00:00:00', $timezone);
                $end = $start->modify('+1 day');
            } else {
                $startsAt = substr((string) ($rule['starts_at'] ?? ''), 0, 5);
                $endsAt = substr((string) ($rule['ends_at'] ?? ''), 0, 5);
                if (!$this->validTime($startsAt) || !$this->validTime($endsAt) || $startsAt >= $endsAt) {
                    continue;
                }
                $start = new DateTimeImmutable($date . ' ' . $startsAt, $timezone);
                $end = new DateTimeImmutable($date . ' ' . $endsAt, $timezone);
            }

            $periods[] = [
                'starts_at' => $start->format('Y-m-d H:i:s'),
                'ends_at' => $end->format('Y-m-d H:i:s'),
            ];
        }

        return $periods;
    }

    private function date(string $value): DateTimeImmutable
    {
        $value = trim($value);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));
        if (!$date || $date->format('Y-m-d') !== $value) {
            throw new \RuntimeException('Informe datas válidas para a ausência.');
        }
        return $date;
    }

    private function validTime(string $value): bool
    {
        return preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value) === 1;
    }

    private function reason(string $reason): ?string
    {
        $reason = trim($reason);
        if ($reason === '') {
            return null;
        }
        return function_exists('mb_substr') ? mb_substr($reason, 0, 190) : substr($reason, 0, 190);
    }
}
