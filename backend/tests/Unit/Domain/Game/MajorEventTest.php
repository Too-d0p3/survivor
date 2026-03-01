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

final class MajorEventTest extends TestCase
{
    public function testConstructorSetsAllFields(): void
    {
        $game = $this->createGame();
        $sourceEvent = $this->createSourceEvent($game);
        $now = new DateTimeImmutable('2026-01-01 12:00:00');

        $majorEvent = new MajorEvent(
            $game,
            $sourceEvent,
            MajorEventType::Alliance,
            'Alex a Bara uzavřeli alianci.',
            7,
            2,
            10,
            3,
            $now,
        );

        self::assertNotEmpty($majorEvent->getId()->toRfc4122());
        self::assertSame($game, $majorEvent->getGame());
        self::assertSame($sourceEvent, $majorEvent->getSourceEvent());
        self::assertSame(MajorEventType::Alliance, $majorEvent->getType());
        self::assertSame('Alex a Bara uzavřeli alianci.', $majorEvent->getSummary());
        self::assertSame(7, $majorEvent->getEmotionalWeight());
        self::assertSame(2, $majorEvent->getDay());
        self::assertSame(10, $majorEvent->getHour());
        self::assertSame(3, $majorEvent->getTick());
        self::assertSame($now, $majorEvent->getCreatedAt());
        self::assertSame([], $majorEvent->getParticipants());
    }

    public function testConstructorClampsEmotionalWeightBelow1(): void
    {
        $game = $this->createGame();
        $sourceEvent = $this->createSourceEvent($game);
        $now = new DateTimeImmutable();

        $majorEvent = new MajorEvent(
            $game,
            $sourceEvent,
            MajorEventType::Conflict,
            'Konflikt na ostrově.',
            0,
            1,
            8,
            1,
            $now,
        );

        self::assertSame(1, $majorEvent->getEmotionalWeight());
    }

    public function testConstructorClampsEmotionalWeightAbove10(): void
    {
        $game = $this->createGame();
        $sourceEvent = $this->createSourceEvent($game);
        $now = new DateTimeImmutable();

        $majorEvent = new MajorEvent(
            $game,
            $sourceEvent,
            MajorEventType::Betrayal,
            'Velká zrada.',
            15,
            1,
            8,
            1,
            $now,
        );

        self::assertSame(10, $majorEvent->getEmotionalWeight());
    }

    public function testConstructorTruncatesSummaryToMaxLength(): void
    {
        $game = $this->createGame();
        $sourceEvent = $this->createSourceEvent($game);
        $now = new DateTimeImmutable();
        $longSummary = str_repeat('a', 250);

        $majorEvent = new MajorEvent(
            $game,
            $sourceEvent,
            MajorEventType::Other,
            $longSummary,
            5,
            1,
            8,
            1,
            $now,
        );

        self::assertSame(200, mb_strlen($majorEvent->getSummary()));
        self::assertSame(str_repeat('a', 200), $majorEvent->getSummary());
    }

    public function testAddParticipantAddsToCollection(): void
    {
        $game = $this->createGame();
        $sourceEvent = $this->createSourceEvent($game);
        $now = new DateTimeImmutable();

        $majorEvent = new MajorEvent(
            $game,
            $sourceEvent,
            MajorEventType::Alliance,
            'Aliance.',
            5,
            1,
            8,
            1,
            $now,
        );

        $player = new Player('Alex', $game);
        $participant = new MajorEventParticipant($majorEvent, $player, ParticipantRole::Initiator);

        $majorEvent->addParticipant($participant);

        self::assertCount(1, $majorEvent->getParticipants());
        self::assertSame($participant, $majorEvent->getParticipants()[0]);
    }

    public function testAddParticipantDoesNotAddDuplicate(): void
    {
        $game = $this->createGame();
        $sourceEvent = $this->createSourceEvent($game);
        $now = new DateTimeImmutable();

        $majorEvent = new MajorEvent(
            $game,
            $sourceEvent,
            MajorEventType::Alliance,
            'Aliance.',
            5,
            1,
            8,
            1,
            $now,
        );

        $player = new Player('Alex', $game);
        $participant = new MajorEventParticipant($majorEvent, $player, ParticipantRole::Initiator);

        $majorEvent->addParticipant($participant);
        $majorEvent->addParticipant($participant);

        self::assertCount(1, $majorEvent->getParticipants());
    }

    private function createGame(): Game
    {
        $owner = new User('owner@example.com');

        return new Game($owner, GameStatus::Setup, new DateTimeImmutable());
    }

    private function createSourceEvent(Game $game): GameEvent
    {
        return new GameEvent(
            $game,
            GameEventType::TickSimulation,
            1,
            8,
            1,
            new DateTimeImmutable(),
        );
    }
}
