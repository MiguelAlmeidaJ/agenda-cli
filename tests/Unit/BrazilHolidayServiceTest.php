<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\BrazilHolidayService;
use PHPUnit\Framework\TestCase;

final class BrazilHolidayServiceTest extends TestCase
{
    public function testIncludesMobileDatesFor2026(): void
    {
        $holidays = (new BrazilHolidayService())->forYear(2026);

        self::assertArrayHasKey('2026-02-16', $holidays);
        self::assertArrayHasKey('2026-02-17', $holidays);
        self::assertSame('Paixão de Cristo', $holidays['2026-04-03']);
        self::assertArrayHasKey('2026-06-04', $holidays);
    }

    public function testCalculatesDifferentEasterCycleFor2027(): void
    {
        $holidays = (new BrazilHolidayService())->forYear(2027);

        self::assertArrayHasKey('2027-02-08', $holidays);
        self::assertArrayHasKey('2027-02-09', $holidays);
        self::assertArrayHasKey('2027-03-26', $holidays);
        self::assertArrayHasKey('2027-05-27', $holidays);
    }
}
