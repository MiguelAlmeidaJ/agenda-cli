<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\EstablishmentClock;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

final class EstablishmentClockTest extends TestCase
{
    public function testDayBoundsRespectReferenceTimezone(): void
    {
        $clock = new EstablishmentClock();
        $reference = new DateTimeImmutable('2026-09-21 23:45:00', new DateTimeZone('America/Sao_Paulo'));

        [$start, $end] = $clock->dayBounds($reference);

        self::assertSame('2026-09-21 00:00:00', $clock->sql($start));
        self::assertSame('2026-09-22 00:00:00', $clock->sql($end));
        self::assertSame('America/Sao_Paulo', $start->getTimezone()->getName());
    }

    public function testMonthBoundsCrossYearWithoutServerTimezone(): void
    {
        $clock = new EstablishmentClock();
        $reference = new DateTimeImmutable('2026-01-15 12:00:00', new DateTimeZone('Pacific/Auckland'));

        [$currentStart, $currentEnd] = $clock->monthBounds($reference);
        [$previousStart, $previousEnd] = $clock->previousMonthBounds($reference);

        self::assertSame('2026-01-01 00:00:00', $clock->sql($currentStart));
        self::assertSame('2026-02-01 00:00:00', $clock->sql($currentEnd));
        self::assertSame('2025-12-01 00:00:00', $clock->sql($previousStart));
        self::assertSame('2026-01-01 00:00:00', $clock->sql($previousEnd));
    }
}
