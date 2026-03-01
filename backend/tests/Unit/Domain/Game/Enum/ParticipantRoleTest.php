<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Game\Enum;

use App\Domain\Game\Enum\ParticipantRole;
use PHPUnit\Framework\TestCase;

final class ParticipantRoleTest extends TestCase
{
    public function testTryFromValidValues(): void
    {
        $values = [
            'initiator' => ParticipantRole::Initiator,
            'target' => ParticipantRole::Target,
            'witness' => ParticipantRole::Witness,
        ];

        foreach ($values as $value => $expectedCase) {
            self::assertSame($expectedCase, ParticipantRole::tryFrom($value), "Expected {$value} to map to {$expectedCase->name}");
        }
    }

    public function testTryFromInvalidReturnsNull(): void
    {
        /** @var array<int, string> $invalidValues */
        $invalidValues = ['invalid', '', 'INITIATOR', 'observer'];

        foreach ($invalidValues as $value) {
            self::assertNull(ParticipantRole::tryFrom($value), "Expected {$value} to return null");
        }
    }
}
