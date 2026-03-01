<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Game\Enum;

use App\Domain\Game\Enum\MajorEventType;
use PHPUnit\Framework\TestCase;

final class MajorEventTypeTest extends TestCase
{
    public function testTryFromValidValues(): void
    {
        $values = [
            'betrayal' => MajorEventType::Betrayal,
            'alliance' => MajorEventType::Alliance,
            'conflict' => MajorEventType::Conflict,
            'revelation' => MajorEventType::Revelation,
            'sacrifice' => MajorEventType::Sacrifice,
            'manipulation' => MajorEventType::Manipulation,
            'other' => MajorEventType::Other,
        ];

        foreach ($values as $value => $expectedCase) {
            self::assertSame($expectedCase, MajorEventType::tryFrom($value), "Expected {$value} to map to {$expectedCase->name}");
        }
    }

    public function testTryFromInvalidReturnsNull(): void
    {
        /** @var array<int, string> $invalidValues */
        $invalidValues = ['invalid', '', 'BETRAYAL', 'unknown_type'];

        foreach ($invalidValues as $value) {
            self::assertNull(MajorEventType::tryFrom($value), "Expected {$value} to return null");
        }
    }
}
