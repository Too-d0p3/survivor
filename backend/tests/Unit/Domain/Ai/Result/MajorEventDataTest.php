<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Ai\Result;

use App\Domain\Ai\Result\MajorEventData;
use App\Domain\Ai\Result\MajorEventParticipantData;
use PHPUnit\Framework\TestCase;

final class MajorEventDataTest extends TestCase
{
    public function testConstructorAndPublicProperties(): void
    {
        $participant1 = new MajorEventParticipantData(2, 'initiator');
        $participant2 = new MajorEventParticipantData(3, 'target');

        $data = new MajorEventData(
            'alliance',
            'Alex a Bara uzavřeli alianci.',
            7,
            [$participant1, $participant2],
        );

        self::assertSame('alliance', $data->type);
        self::assertSame('Alex a Bara uzavřeli alianci.', $data->summary);
        self::assertSame(7, $data->emotionalWeight);
        self::assertCount(2, $data->participants);
        self::assertSame($participant1, $data->participants[0]);
        self::assertSame($participant2, $data->participants[1]);
    }
}
