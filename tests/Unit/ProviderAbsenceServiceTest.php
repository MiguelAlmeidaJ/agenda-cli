<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\ProviderAbsenceService;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

final class ProviderAbsenceServiceTest extends TestCase
{
    public function testExpandsVacationAsFullDay(): void
    {
        $service = new ProviderAbsenceService();
        $rule = $service->normalizeDateRange('2026-10-10', '2026-10-20', 'Férias');
        $day = new DateTimeImmutable('2026-10-15 00:00:00', new DateTimeZone('America/Sao_Paulo'));

        self::assertSame([
            [
                'starts_at' => '2026-10-15 00:00:00',
                'ends_at' => '2026-10-16 00:00:00',
            ],
        ], $service->periodsForDate([$rule], $day));
    }

    public function testWeeklyRuleAppliesOnlyOnConfiguredWeekday(): void
    {
        $service = new ProviderAbsenceService();
        $rule = $service->normalizeWeekly(2, '2026-09-01', null, '12:00', '14:00', 'Almoço fixo');

        $tuesday = new DateTimeImmutable('2026-09-22 00:00:00', new DateTimeZone('America/Sao_Paulo'));
        $wednesday = new DateTimeImmutable('2026-09-23 00:00:00', new DateTimeZone('America/Sao_Paulo'));

        self::assertSame('2026-09-22 12:00:00', $service->periodsForDate([$rule], $tuesday)[0]['starts_at']);
        self::assertSame([], $service->periodsForDate([$rule], $wednesday));
    }

    public function testRejectsInvertedVacationRange(): void
    {
        $this->expectException(\RuntimeException::class);
        (new ProviderAbsenceService())->normalizeDateRange('2026-10-20', '2026-10-10');
    }
}
