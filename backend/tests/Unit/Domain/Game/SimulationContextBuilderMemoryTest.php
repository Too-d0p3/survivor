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
use App\Domain\Game\SimulationContextBuilder;
use App\Domain\Player\Player;
use App\Domain\User\User;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class SimulationContextBuilderMemoryTest extends TestCase
{
    public function testBuildMemoryInputsWithEvents(): void
    {
        $owner = new User('owner@example.com');
        $game = new Game($owner, GameStatus::Setup, new DateTimeImmutable());

        $players = [
            new Player('Ondra', $game, $owner),
            new Player('Alex', $game),
            new Player('Bara', $game),
        ];

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
            'Alex a Bara uzavřeli alianci.',
            7,
            1,
            8,
            1,
            new DateTimeImmutable(),
        );

        // Player at index 2 (Alex) is the initiator
        $participant = new MajorEventParticipant($majorEvent, $players[1], ParticipantRole::Initiator);
        $majorEvent->addParticipant($participant);

        // majorEventsByPlayerIndex: playerIndex=2 → [majorEvent]
        $majorEventsByPlayerIndex = [2 => [$majorEvent]];

        $inputs = SimulationContextBuilder::buildMemoryInputs($majorEventsByPlayerIndex, $players);

        self::assertCount(1, $inputs);
        self::assertSame(2, $inputs[0]->getPlayerIndex());
        self::assertSame(1, $inputs[0]->getDay());
        self::assertSame(8, $inputs[0]->getHour());
        self::assertSame('alliance', $inputs[0]->getType());
        self::assertSame('Alex a Bara uzavřeli alianci.', $inputs[0]->getSummary());
        self::assertSame(7, $inputs[0]->getEmotionalWeight());
        self::assertSame('iniciátor', $inputs[0]->getRole());
    }

    public function testBuildMemoryInputsEmptyEvents(): void
    {
        $owner = new User('owner@example.com');
        $game = new Game($owner, GameStatus::Setup, new DateTimeImmutable());
        $players = [
            new Player('Ondra', $game, $owner),
            new Player('Alex', $game),
        ];

        $inputs = SimulationContextBuilder::buildMemoryInputs([], $players);

        self::assertSame([], $inputs);
    }

    public function testBuildMemoryInputsRoleMapping(): void
    {
        $owner = new User('owner@example.com');
        $game = new Game($owner, GameStatus::Setup, new DateTimeImmutable());

        $players = [
            new Player('Ondra', $game, $owner),
            new Player('Alex', $game),
            new Player('Bara', $game),
            new Player('Cyril', $game),
        ];

        $sourceEvent = new GameEvent(
            $game,
            GameEventType::TickSimulation,
            1,
            8,
            1,
            new DateTimeImmutable(),
        );

        // Create three events, one per role
        $eventInitiator = new MajorEvent($game, $sourceEvent, MajorEventType::Betrayal, 'Zrada.', 8, 1, 8, 1, new DateTimeImmutable());
        $eventTarget = new MajorEvent($game, $sourceEvent, MajorEventType::Conflict, 'Konflikt.', 6, 1, 10, 1, new DateTimeImmutable());
        $eventWitness = new MajorEvent($game, $sourceEvent, MajorEventType::Revelation, 'Odhalení.', 4, 1, 12, 1, new DateTimeImmutable());

        $participantInitiator = new MajorEventParticipant($eventInitiator, $players[1], ParticipantRole::Initiator);
        $eventInitiator->addParticipant($participantInitiator);

        $participantTarget = new MajorEventParticipant($eventTarget, $players[2], ParticipantRole::Target);
        $eventTarget->addParticipant($participantTarget);

        $participantWitness = new MajorEventParticipant($eventWitness, $players[3], ParticipantRole::Witness);
        $eventWitness->addParticipant($participantWitness);

        $majorEventsByPlayerIndex = [
            2 => [$eventInitiator],
            3 => [$eventTarget],
            4 => [$eventWitness],
        ];

        $inputs = SimulationContextBuilder::buildMemoryInputs($majorEventsByPlayerIndex, $players);

        self::assertCount(3, $inputs);

        // Find each input by playerIndex
        $byPlayerIndex = [];
        foreach ($inputs as $input) {
            $byPlayerIndex[$input->getPlayerIndex()] = $input;
        }

        self::assertSame('iniciátor', $byPlayerIndex[2]->getRole());
        self::assertSame('oběť', $byPlayerIndex[3]->getRole());
        self::assertSame('svědek', $byPlayerIndex[4]->getRole());
    }
}
