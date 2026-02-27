<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Game;

use App\Domain\Game\Enum\DayPhase;
use PHPUnit\Framework\TestCase;

final class DayPhaseTest extends TestCase
{
    public function testFromHourMorningAt6(): void
    {
        self::assertSame(DayPhase::Morning, DayPhase::fromHour(6));
    }

    public function testFromHourMorningAt8(): void
    {
        self::assertSame(DayPhase::Morning, DayPhase::fromHour(8));
    }

    public function testFromHourMorningAt10(): void
    {
        self::assertSame(DayPhase::Morning, DayPhase::fromHour(10));
    }

    public function testFromHourAfternoonAt12(): void
    {
        self::assertSame(DayPhase::Afternoon, DayPhase::fromHour(12));
    }

    public function testFromHourAfternoonAt14(): void
    {
        self::assertSame(DayPhase::Afternoon, DayPhase::fromHour(14));
    }

    public function testFromHourAfternoonAt16(): void
    {
        self::assertSame(DayPhase::Afternoon, DayPhase::fromHour(16));
    }

    public function testFromHourEveningAt18(): void
    {
        self::assertSame(DayPhase::Evening, DayPhase::fromHour(18));
    }

    public function testFromHourEveningAt20(): void
    {
        self::assertSame(DayPhase::Evening, DayPhase::fromHour(20));
    }

    public function testFromHourEveningAt22(): void
    {
        self::assertSame(DayPhase::Evening, DayPhase::fromHour(22));
    }

    public function testFromHourNightAt0(): void
    {
        self::assertSame(DayPhase::Night, DayPhase::fromHour(0));
    }

    public function testFromHourNightAt2(): void
    {
        self::assertSame(DayPhase::Night, DayPhase::fromHour(2));
    }

    public function testFromHourNightAt4(): void
    {
        self::assertSame(DayPhase::Night, DayPhase::fromHour(4));
    }
}
