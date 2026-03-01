<?php

declare(strict_types=1);

namespace App\Tests\Integration\Domain\Game;

use App\Domain\Game\Enum\GameEventType;
use App\Domain\Game\Enum\MajorEventType;
use App\Domain\Game\Enum\ParticipantRole;
use App\Domain\Game\Game;
use App\Domain\Game\GameEvent;
use App\Domain\Game\GameStatus;
use App\Domain\Game\MajorEvent;
use App\Domain\Game\MajorEventParticipant;
use App\Domain\Game\MajorEventRepository;
use App\Domain\Player\Player;
use App\Tests\Integration\AbstractIntegrationTestCase;
use DateTimeImmutable;

final class MajorEventRepositoryTest extends AbstractIntegrationTestCase
{
    public function testFindByGameForPlayerReturnsMatchingEvents(): void
    {
        $user = $this->createAndPersistUser();
        $em = $this->getEntityManager();

        $game = new Game($user, GameStatus::Setup, new DateTimeImmutable());
        $em->persist($game);

        $playerAlex = new Player('Alex', $game);
        $playerBara = new Player('Bara', $game);
        $em->persist($playerAlex);
        $em->persist($playerBara);

        $sourceEvent = new GameEvent($game, GameEventType::TickSimulation, 1, 8, 1, new DateTimeImmutable());
        $em->persist($sourceEvent);

        // Event with Alex as participant
        $eventWithAlex = new MajorEvent($game, $sourceEvent, MajorEventType::Alliance, 'Alex uzavřel alianci.', 7, 1, 8, 1, new DateTimeImmutable());
        $em->persist($eventWithAlex);
        $participantAlex = new MajorEventParticipant($eventWithAlex, $playerAlex, ParticipantRole::Initiator);
        $em->persist($participantAlex);
        $eventWithAlex->addParticipant($participantAlex);

        // Event without Alex (only Bara)
        $eventWithoutAlex = new MajorEvent($game, $sourceEvent, MajorEventType::Conflict, 'Bara měla konflikt.', 5, 1, 10, 1, new DateTimeImmutable());
        $em->persist($eventWithoutAlex);
        $participantBara = new MajorEventParticipant($eventWithoutAlex, $playerBara, ParticipantRole::Target);
        $em->persist($participantBara);
        $eventWithoutAlex->addParticipant($participantBara);

        $em->flush();

        $repository = $this->getService(MajorEventRepository::class);
        $results = $repository->findByGameForPlayer($game->getId(), $playerAlex->getId());

        self::assertCount(1, $results);
        self::assertSame('Alex uzavřel alianci.', $results[0]->getSummary());
    }

    public function testFindByGameForPlayerOrdersByEmotionalWeightAndTick(): void
    {
        $user = $this->createAndPersistUser();
        $em = $this->getEntityManager();

        $game = new Game($user, GameStatus::Setup, new DateTimeImmutable());
        $em->persist($game);

        $player = new Player('Alex', $game);
        $em->persist($player);

        $sourceEvent = new GameEvent($game, GameEventType::TickSimulation, 1, 8, 1, new DateTimeImmutable());
        $em->persist($sourceEvent);

        // Low weight event at tick 1
        $lowWeightEvent = new MajorEvent($game, $sourceEvent, MajorEventType::Other, 'Malá událost.', 3, 1, 8, 1, new DateTimeImmutable());
        $em->persist($lowWeightEvent);
        $p1 = new MajorEventParticipant($lowWeightEvent, $player, ParticipantRole::Witness);
        $em->persist($p1);
        $lowWeightEvent->addParticipant($p1);

        // High weight event at tick 2
        $highWeightEvent = new MajorEvent($game, $sourceEvent, MajorEventType::Betrayal, 'Velká zrada.', 9, 1, 10, 2, new DateTimeImmutable());
        $em->persist($highWeightEvent);
        $p2 = new MajorEventParticipant($highWeightEvent, $player, ParticipantRole::Target);
        $em->persist($p2);
        $highWeightEvent->addParticipant($p2);

        $em->flush();

        $repository = $this->getService(MajorEventRepository::class);
        $results = $repository->findByGameForPlayer($game->getId(), $player->getId());

        self::assertCount(2, $results);
        // High weight (9) should come first
        self::assertSame('Velká zrada.', $results[0]->getSummary());
        self::assertSame('Malá událost.', $results[1]->getSummary());
    }

    public function testFindByGameForPlayerLimitsResults(): void
    {
        $user = $this->createAndPersistUser();
        $em = $this->getEntityManager();

        $game = new Game($user, GameStatus::Setup, new DateTimeImmutable());
        $em->persist($game);

        $player = new Player('Alex', $game);
        $em->persist($player);

        $sourceEvent = new GameEvent($game, GameEventType::TickSimulation, 1, 8, 1, new DateTimeImmutable());
        $em->persist($sourceEvent);

        for ($i = 1; $i <= 8; $i++) {
            $event = new MajorEvent($game, $sourceEvent, MajorEventType::Other, "Událost {$i}.", $i, 1, 6 + $i, $i, new DateTimeImmutable());
            $em->persist($event);
            $participant = new MajorEventParticipant($event, $player, ParticipantRole::Witness);
            $em->persist($participant);
            $event->addParticipant($participant);
        }

        $em->flush();

        $repository = $this->getService(MajorEventRepository::class);
        $results = $repository->findByGameForPlayer($game->getId(), $player->getId(), 3);

        self::assertCount(3, $results);
    }

    public function testFindByGameAndTickReturnsMatchingEvents(): void
    {
        $user = $this->createAndPersistUser();
        $em = $this->getEntityManager();

        $game = new Game($user, GameStatus::Setup, new DateTimeImmutable());
        $em->persist($game);

        $player = new Player('Alex', $game);
        $em->persist($player);

        $sourceEvent = new GameEvent($game, GameEventType::TickSimulation, 1, 8, 1, new DateTimeImmutable());
        $em->persist($sourceEvent);

        // Event at tick 1
        $eventTick1 = new MajorEvent($game, $sourceEvent, MajorEventType::Alliance, 'Aliance v ticku 1.', 6, 1, 8, 1, new DateTimeImmutable());
        $em->persist($eventTick1);
        $p1 = new MajorEventParticipant($eventTick1, $player, ParticipantRole::Initiator);
        $em->persist($p1);
        $eventTick1->addParticipant($p1);

        // Event at tick 2
        $eventTick2 = new MajorEvent($game, $sourceEvent, MajorEventType::Conflict, 'Konflikt v ticku 2.', 5, 1, 10, 2, new DateTimeImmutable());
        $em->persist($eventTick2);
        $p2 = new MajorEventParticipant($eventTick2, $player, ParticipantRole::Target);
        $em->persist($p2);
        $eventTick2->addParticipant($p2);

        $em->flush();

        $repository = $this->getService(MajorEventRepository::class);
        $results = $repository->findByGameAndTick($game->getId(), 1);

        self::assertCount(1, $results);
        self::assertSame('Aliance v ticku 1.', $results[0]->getSummary());
    }

    public function testFindByGameReturnsAllEventsOrderedByTick(): void
    {
        $user = $this->createAndPersistUser();
        $em = $this->getEntityManager();

        $game = new Game($user, GameStatus::Setup, new DateTimeImmutable());
        $em->persist($game);

        $player = new Player('Alex', $game);
        $em->persist($player);

        $sourceEvent = new GameEvent($game, GameEventType::TickSimulation, 1, 8, 1, new DateTimeImmutable());
        $em->persist($sourceEvent);

        // Create events in non-chronological order
        $eventTick3 = new MajorEvent($game, $sourceEvent, MajorEventType::Betrayal, 'Událost v ticku 3.', 8, 2, 8, 3, new DateTimeImmutable());
        $em->persist($eventTick3);
        $p3 = new MajorEventParticipant($eventTick3, $player, ParticipantRole::Target);
        $em->persist($p3);
        $eventTick3->addParticipant($p3);

        $eventTick1 = new MajorEvent($game, $sourceEvent, MajorEventType::Alliance, 'Událost v ticku 1.', 5, 1, 8, 1, new DateTimeImmutable());
        $em->persist($eventTick1);
        $p1 = new MajorEventParticipant($eventTick1, $player, ParticipantRole::Initiator);
        $em->persist($p1);
        $eventTick1->addParticipant($p1);

        $eventTick2 = new MajorEvent($game, $sourceEvent, MajorEventType::Conflict, 'Událost v ticku 2.', 6, 1, 10, 2, new DateTimeImmutable());
        $em->persist($eventTick2);
        $p2 = new MajorEventParticipant($eventTick2, $player, ParticipantRole::Witness);
        $em->persist($p2);
        $eventTick2->addParticipant($p2);

        $em->flush();

        $repository = $this->getService(MajorEventRepository::class);
        $results = $repository->findByGame($game->getId());

        self::assertCount(3, $results);
        self::assertSame('Událost v ticku 1.', $results[0]->getSummary());
        self::assertSame('Událost v ticku 2.', $results[1]->getSummary());
        self::assertSame('Událost v ticku 3.', $results[2]->getSummary());
    }
}
