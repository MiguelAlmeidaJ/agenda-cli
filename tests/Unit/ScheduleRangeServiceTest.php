<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\ScheduleRangeService;
use PHPUnit\Framework\TestCase;

final class ScheduleRangeServiceTest extends TestCase
{
    public function testNormalizesAndSortsRanges(): void
    {
        $ranges = (new ScheduleRangeService())->normalize([
            ['opens' => '14:00', 'closes' => '18:00'],
            ['opens' => '08:00', 'closes' => '12:00'],
        ]);

        self::assertSame([
            ['opens_at' => '08:00', 'closes_at' => '12:00'],
            ['opens_at' => '14:00', 'closes_at' => '18:00'],
        ], $ranges);
    }

    public function testRejectsOverlappingRanges(): void
    {
        $this->expectException(\RuntimeException::class);
        (new ScheduleRangeService())->normalize([
            ['opens' => '08:00', 'closes' => '12:00'],
            ['opens' => '11:30', 'closes' => '18:00'],
        ]);
    }

    public function testIntersectsProviderAndEstablishmentRanges(): void
    {
        $ranges = (new ScheduleRangeService())->intersect(
            [
                ['opens_at' => '08:00', 'closes_at' => '12:00'],
                ['opens_at' => '14:00', 'closes_at' => '18:00'],
            ],
            [
                ['opens_at' => '10:00', 'closes_at' => '16:00'],
            ]
        );

        self::assertSame([
            ['opens_at' => '10:00', 'closes_at' => '12:00'],
            ['opens_at' => '14:00', 'closes_at' => '16:00'],
        ], $ranges);
    }
}
