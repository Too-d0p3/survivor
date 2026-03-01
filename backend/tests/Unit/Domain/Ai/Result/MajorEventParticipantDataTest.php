<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Ai\Result;

use App\Domain\Ai\Result\MajorEventParticipantData;
use PHPUnit\Framework\TestCase;

final class MajorEventParticipantDataTest extends TestCase
{
    public function testConstructorAndPublicProperties(): void
    {
        $data = new MajorEventParticipantData(3, 'target');

        self::assertSame(3, $data->playerIndex);
        self::assertSame('target', $data->role);
    }
}
