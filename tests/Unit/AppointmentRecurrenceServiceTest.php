<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\AppointmentRecurrenceService;
use PHPUnit\Framework\TestCase;

final class AppointmentRecurrenceServiceTest extends TestCase
{
    public function testBuildsWeeklyDates(): void
    {
        $dates = (new AppointmentRecurrenceService())->dates('2026-09-24', 1, 4, 'America/Sao_Paulo');

        self::assertSame([
            '2026-09-24',
            '2026-10-01',
            '2026-10-08',
            '2026-10-15',
        ], $dates);
    }

    public function testBuildsBiweeklyDates(): void
    {
        $dates = (new AppointmentRecurrenceService())->dates('2026-09-24', 2, 3, 'America/Sao_Paulo');

        self::assertSame([
            '2026-09-24',
            '2026-10-08',
            '2026-10-22',
        ], $dates);
    }

    public function testRejectsTooManyOccurrences(): void
    {
        $this->expectException(\RuntimeException::class);
        (new AppointmentRecurrenceService())->dates('2026-09-24', 1, 53, 'America/Sao_Paulo');
    }
}
