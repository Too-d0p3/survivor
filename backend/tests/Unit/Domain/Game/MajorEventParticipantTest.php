<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Game;

use App\Domain\Game\Enum\GameEventType;
use App\Domain\Game\Enum\MajorEventType;
use App\Domain\Game\Enum\ParticipantRole;
use App\Domain\Game\Game;
use App\Domain\Game\GameEvent;
use App\Domain\Game\GameStatus;
use App\Domain\Game\MajorEvent;
use App\Domain\Game\MajorEventParticipant;
use App\Domain\Player\Player;
use App\Domain\User\User;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class MajorEventParticipantTest extends TestCase
{
    public function testConstructorSetsAllFields(): void
    {
        $owner = new User('owner@example.com');
        $game = new Game($owner, GameStatus::Setup, new DateTimeImmutable());

        $sourceEvent = new GameEvent(
            $game,
            GameEventType::TickSimulation,
            1,
            8,
            1,
            new DateTimeImmutable(),
        );

        $majorEvent = new MajorEvent(
            $game,
            $sourceEvent,
            MajorEventType::Alliance,
            'Aliance.',
            5,
            1,
            8,
            1,
            new DateTimeImmutable(),
        );

        $player = new Player('Alex', $game);

        $participant = new MajorEventParticipant($majorEvent, $player, ParticipantRole::Initiator);

        self::assertNotEmpty($participant->getId()->toRfc4122());
        self::assertSame($majorEvent, $participant->getMajorEvent());
        self::assertSame($player, $participant->getPlayer());
        self::assertSame(ParticipantRole::Initiator, $participant->getRole());
    }
}
