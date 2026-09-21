<?php

declare(strict_types=1);

namespace App\Services;

final class ScheduleRangeService
{
    public function normalize(array $ranges): array
    {
        $normalized = [];

        foreach (array_values($ranges) as $range) {
            if (!is_array($range)) {
                continue;
            }

            $opens = trim((string) ($range['opens_at'] ?? $range['opens'] ?? ''));
            $closes = trim((string) ($range['closes_at'] ?? $range['closes'] ?? ''));

            if ($opens === '' && $closes === '') {
                continue;
            }
            if (!$this->validTime($opens) || !$this->validTime($closes) || $opens >= $closes) {
                throw new \RuntimeException('Cada faixa precisa ter um início e fim válidos.');
            }

            $normalized[] = [
                'opens_at' => $opens,
                'closes_at' => $closes,
            ];
        }

        if (count($normalized) > 8) {
            throw new \RuntimeException('Use no máximo 8 faixas de horário por dia.');
        }

        usort($normalized, static fn (array $a, array $b): int => strcmp($a['opens_at'], $b['opens_at']));

        $previousEnd = null;
        foreach ($normalized as $range) {
            if ($previousEnd !== null && $range['opens_at'] < $previousEnd) {
                throw new \RuntimeException('As faixas de horário não podem se sobrepor.');
            }
            $previousEnd = $range['closes_at'];
        }

        return $normalized;
    }

    public function intersect(array $left, array $right): array
    {
        $result = [];

        foreach ($left as $a) {
            foreach ($right as $b) {
                $opens = max(substr((string) $a['opens_at'], 0, 5), substr((string) $b['opens_at'], 0, 5));
                $closes = min(substr((string) $a['closes_at'], 0, 5), substr((string) $b['closes_at'], 0, 5));
                if ($opens < $closes) {
                    $result[] = ['opens_at' => $opens, 'closes_at' => $closes];
                }
            }
        }

        if ($result === []) {
            return [];
        }

        usort($result, static fn (array $a, array $b): int => strcmp($a['opens_at'], $b['opens_at']));
        $merged = [];
        foreach ($result as $range) {
            $lastIndex = count($merged) - 1;
            if ($lastIndex >= 0 && $range['opens_at'] <= $merged[$lastIndex]['closes_at']) {
                if ($range['closes_at'] > $merged[$lastIndex]['closes_at']) {
                    $merged[$lastIndex]['closes_at'] = $range['closes_at'];
                }
                continue;
            }
            $merged[] = $range;
        }

        return $merged;
    }

    public function summary(array $ranges): string
    {
        if ($ranges === []) {
            return 'Fechado';
        }

        return implode(' / ', array_map(
            static fn (array $range): string => substr((string) $range['opens_at'], 0, 5) . '–' . substr((string) $range['closes_at'], 0, 5),
            $ranges
        ));
    }

    private function validTime(string $time): bool
    {
        return preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time) === 1;
    }
}
