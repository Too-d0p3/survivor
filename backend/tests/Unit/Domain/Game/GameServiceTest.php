<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Game;

use App\Domain\Game\Enum\GameEventType;
use App\Domain\Game\Exceptions\CannotDeleteGameBecauseUserIsNotOwnerException;
use App\Domain\Game\Exceptions\CannotProcessTickBecauseGameIsNotInProgressException;
use App\Domain\Game\Exceptions\CannotStartGameBecauseUserIsNotOwnerException;
use App\Domain\Game\Game;
use App\Domain\Game\GameService;
use App\Domain\Game\GameStatus;
use App\Domain\Player\Player;
use App\Domain\TraitDef\TraitDef;
use App\Domain\TraitDef\TraitType;
use App\Domain\User\User;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class GameServiceTest extends TestCase
{
    private GameService $gameService;

    public function testDeleteGameWhenUserIsOwnerReturnsGame(): void
    {
        $owner = new User('owner@example.com');
        $game = new Game($owner, GameStatus::Setup, new DateTimeImmutable());

        $result = $this->gameService->deleteGame($game, $owner);

        self::assertSame($game, $result);
    }

    public function testDeleteGameWhenUserIsNotOwnerThrowsException(): void
    {
        $owner = new User('owner@example.com');
        $otherUser = new User('other@example.com');
        $game = new Game($owner, GameStatus::Setup, new DateTimeImmutable());

        $this->expectException(CannotDeleteGameBecauseUserIsNotOwnerException::class);

        $this->gameService->deleteGame($game, $otherUser);
    }

    public function testCreateGameReturnsGameWithCorrectOwnerAndStatus(): void
    {
        [$traitDefs, $aiTraitStrengths, $aiDescriptions] = $this->createAiPlayerData();
        $owner = new User('owner@example.com');
        $now = new DateTimeImmutable('2026-02-01 12:00:00');

        $result = $this->gameService->createGame(
            $owner,
            'Human Player',
            'A brave leader',
            ['leadership' => '0.90', 'empathy' => '0.70'],
            $traitDefs,
            $aiTraitStrengths,
            $aiDescriptions,
            $now,
        );

        self::assertSame($owner, $result->game->getOwner());
        self::assertSame(GameStatus::Setup, $result->game->getStatus());
        self::assertSame($now, $result->game->getCreatedAt());
    }

    public function testCreateGameCreatesHumanPlayerLinkedToUser(): void
    {
        [$traitDefs, $aiTraitStrengths, $aiDescriptions] = $this->createAiPlayerData();
        $owner = new User('owner@example.com');
        $now = new DateTimeImmutable();

        $result = $this->gameService->createGame(
            $owner,
            'Human Player',
            'A brave leader',
            ['leadership' => '0.90', 'empathy' => '0.70'],
            $traitDefs,
            $aiTraitStrengths,
            $aiDescriptions,
            $now,
        );

        $players = $result->game->getPlayers();
        $humanPlayer = $players[0];

        self::assertTrue($humanPlayer->isHuman());
        self::assertSame($owner, $humanPlayer->getUser());
        self::assertSame('Human Player', $humanPlayer->getName());
        self::assertSame('A brave leader', $humanPlayer->getDescription());
    }

    public function testCreateGameCreatesHumanPlayerWithCorrectTraits(): void
    {
        [$traitDefs, $aiTraitStrengths, $aiDescriptions] = $this->createAiPlayerData();
        $owner = new User('owner@example.com');
        $now = new DateTimeImmutable();

        $result = $this->gameService->createGame(
            $owner,
            'Human Player',
            'A brave leader',
            ['leadership' => '0.90', 'empathy' => '0.70'],
            $traitDefs,
            $aiTraitStrengths,
            $aiDescriptions,
            $now,
        );

        $humanPlayer = $result->game->getPlayers()[0];
        $traits = $humanPlayer->getPlayerTraits();

        self::assertCount(2, $traits);
        self::assertSame('0.90', $traits[0]->getStrength());
        self::assertSame('0.70', $traits[1]->getStrength());
    }

    public function testCreateGameCreatesFiveAiPlayers(): void
    {
        [$traitDefs, $aiTraitStrengths, $aiDescriptions] = $this->createAiPlayerData();
        $owner = new User('owner@example.com');
        $now = new DateTimeImmutable();

        $result = $this->gameService->createGame(
            $owner,
            'Human Player',
            'A brave leader',
            ['leadership' => '0.90'],
            $traitDefs,
            $aiTraitStrengths,
            $aiDescriptions,
            $now,
        );

        $players = $result->game->getPlayers();

        self::assertCount(6, $players);

        $aiPlayers = array_slice($players, 1);
        foreach ($aiPlayers as $aiPlayer) {
            self::assertFalse($aiPlayer->isHuman());
            self::assertNull($aiPlayer->getUser());
        }

        self::assertSame('Alex', $aiPlayers[0]->getName());
        self::assertSame('Bara', $aiPlayers[1]->getName());
        self::assertSame('Cyril', $aiPlayers[2]->getName());
        self::assertSame('Dana', $aiPlayers[3]->getName());
        self::assertSame('Emil', $aiPlayers[4]->getName());
    }

    public function testCreateGameAiPlayersHaveCorrectTraits(): void
    {
        [$traitDefs, $aiTraitStrengths, $aiDescriptions] = $this->createAiPlayerData();
        $owner = new User('owner@example.com');
        $now = new DateTimeImmutable();

        $result = $this->gameService->createGame(
            $owner,
            'Human Player',
            'A brave leader',
            ['leadership' => '0.90'],
            $traitDefs,
            $aiTraitStrengths,
            $aiDescriptions,
            $now,
        );

        $firstAiPlayer = $result->game->getPlayers()[1];
        $traits = $firstAiPlayer->getPlayerTraits();

        self::assertCount(2, $traits);
        self::assertSame('0.50', $traits[0]->getStrength());
        self::assertSame('0.60', $traits[1]->getStrength());
    }

    public function testCreateGameSetsDescriptionsOnAllPlayers(): void
    {
        [$traitDefs, $aiTraitStrengths, $aiDescriptions] = $this->createAiPlayerData();
        $owner = new User('owner@example.com');
        $now = new DateTimeImmutable();

        $result = $this->gameService->createGame(
            $owner,
            'Human Player',
            'A brave leader',
            ['leadership' => '0.90'],
            $traitDefs,
            $aiTraitStrengths,
            $aiDescriptions,
            $now,
        );

        $players = $result->game->getPlayers();

        self::assertSame('A brave leader', $players[0]->getDescription());
        self::assertSame('AI desc 1', $players[1]->getDescription());
        self::assertSame('AI desc 2', $players[2]->getDescription());
        self::assertSame('AI desc 3', $players[3]->getDescription());
        self::assertSame('AI desc 4', $players[4]->getDescription());
        self::assertSame('AI desc 5', $players[5]->getDescription());
    }

    public function testStartGameReturnsStartGameResult(): void
    {
        $owner = new User('owner@example.com');
        $game = new Game($owner, GameStatus::Setup, new DateTimeImmutable());
        $now = new DateTimeImmutable('2026-01-01 12:00:00');

        $result = $this->gameService->startGame($game, $owner, $now);

        self::assertSame($game, $result->game);
        self::assertSame(GameStatus::InProgress, $result->game->getStatus());
        self::assertSame(1, $result->game->getCurrentDay());
        self::assertSame(6, $result->game->getCurrentHour());
        self::assertSame(0, $result->game->getCurrentTick());
    }

    public function testStartGameCreatesGameStartedEvent(): void
    {
        $owner = new User('owner@example.com');
        $game = new Game($owner, GameStatus::Setup, new DateTimeImmutable());
        $now = new DateTimeImmutable('2026-01-01 12:00:00');

        $result = $this->gameService->startGame($game, $owner, $now);

        self::assertCount(1, $result->events);
        $event = $result->events[0];
        self::assertSame(GameEventType::GameStarted, $event->getType());
        self::assertSame(1, $event->getDay());
        self::assertSame(6, $event->getHour());
        self::assertSame(0, $event->getTick());
        self::assertSame($now, $event->getCreatedAt());
    }

    public function testStartGameWhenUserIsNotOwnerThrowsException(): void
    {
        $owner = new User('owner@example.com');
        $otherUser = new User('other@example.com');
        $game = new Game($owner, GameStatus::Setup, new DateTimeImmutable());

        $this->expectException(CannotStartGameBecauseUserIsNotOwnerException::class);

        $this->gameService->startGame($game, $otherUser, new DateTimeImmutable());
    }

    public function testProcessTickCreatesPlayerActionEvent(): void
    {
        $owner = new User('owner@example.com');
        $game = new Game($owner, GameStatus::Setup, new DateTimeImmutable());
        $game->start(new DateTimeImmutable());
        $humanPlayer = new Player('Alice', $game, $owner);
        $now = new DateTimeImmutable('2026-01-01 12:00:00');

        $result = $this->gameService->processTick($game, $humanPlayer, 'Went fishing', $now);

        $playerActionEvents = array_filter(
            $result->events,
            static fn ($e) => $e->getType() === GameEventType::PlayerAction,
        );
        self::assertCount(1, $playerActionEvents);

        $event = reset($playerActionEvents);
        self::assertSame($humanPlayer, $event->getPlayer());
        self::assertSame(['action_text' => 'Went fishing'], $event->getMetadata());
    }

    public function testProcessTickAdvancesTime(): void
    {
        $owner = new User('owner@example.com');
        $game = new Game($owner, GameStatus::Setup, new DateTimeImmutable());
        $game->start(new DateTimeImmutable());
        $humanPlayer = new Player('Alice', $game, $owner);

        $result = $this->gameService->processTick($game, $humanPlayer, 'Went fishing', new DateTimeImmutable());

        self::assertSame(1, $result->game->getCurrentTick());
        self::assertSame(8, $result->game->getCurrentHour());
        self::assertSame(1, $result->game->getCurrentDay());
    }

    public function testProcessTickAtHour22CreatesNightSleepEventAndSleepsToNextDay(): void
    {
        $owner = new User('owner@example.com');
        $game = new Game($owner, GameStatus::Setup, new DateTimeImmutable());
        $game->start(new DateTimeImmutable());
        $humanPlayer = new Player('Alice', $game, $owner);

        // Advance to hour 22 (tick 8, since 6 + 8*2 = 22)
        for ($i = 0; $i < 8; $i++) {
            $this->gameService->processTick($game, $humanPlayer, "Action {$i}", new DateTimeImmutable());
        }

        self::assertSame(22, $game->getCurrentHour());

        // This tick should trigger night sleep (hour goes to 24)
        $result = $this->gameService->processTick($game, $humanPlayer, 'Last action', new DateTimeImmutable());

        $nightSleepEvents = array_filter(
            $result->events,
            static fn ($e) => $e->getType() === GameEventType::NightSleep,
        );
        self::assertCount(1, $nightSleepEvents);

        self::assertSame(2, $result->game->getCurrentDay());
        self::assertSame(6, $result->game->getCurrentHour());
    }

    public function testProcessTickAtHour12DoesNotCreateNightSleepEvent(): void
    {
        $owner = new User('owner@example.com');
        $game = new Game($owner, GameStatus::Setup, new DateTimeImmutable());
        $game->start(new DateTimeImmutable());
        $humanPlayer = new Player('Alice', $game, $owner);

        // Advance to hour 12 (tick 3, since 6 + 3*2 = 12)
        for ($i = 0; $i < 3; $i++) {
            $this->gameService->processTick($game, $humanPlayer, "Action {$i}", new DateTimeImmutable());
        }

        self::assertSame(12, $game->getCurrentHour());

        $result = $this->gameService->processTick($game, $humanPlayer, 'Afternoon action', new DateTimeImmutable());

        $nightSleepEvents = array_filter(
            $result->events,
            static fn ($e) => $e->getType() === GameEventType::NightSleep,
        );
        self::assertCount(0, $nightSleepEvents);

        self::assertSame(1, $result->game->getCurrentDay());
    }

    public function testProcessTickWhenGameNotInProgressThrowsException(): void
    {
        $owner = new User('owner@example.com');
        $game = new Game($owner, GameStatus::Setup, new DateTimeImmutable());
        $humanPlayer = new Player('Alice', $game, $owner);

        $this->expectException(CannotProcessTickBecauseGameIsNotInProgressException::class);

        $this->gameService->processTick($game, $humanPlayer, 'Action', new DateTimeImmutable());
    }

    protected function setUp(): void
    {
        $this->gameService = new GameService();
    }

    /**
     * @return array{array<int, TraitDef>, array<int, array<string, string>>, array<int, string>}
     */
    private function createAiPlayerData(): array
    {
        $traitDefs = [
            new TraitDef('leadership', 'Leadership', 'Leading ability', TraitType::Social),
            new TraitDef('empathy', 'Empathy', 'Understanding others', TraitType::Emotional),
        ];

        $aiTraitStrengths = [];
        for ($i = 0; $i < 5; $i++) {
            $aiTraitStrengths[] = [
                'leadership' => number_format(($i + 5) / 10, 2, '.', ''),
                'empathy' => number_format(($i + 6) / 10, 2, '.', ''),
            ];
        }

        $aiDescriptions = ['AI desc 1', 'AI desc 2', 'AI desc 3', 'AI desc 4', 'AI desc 5'];

        return [$traitDefs, $aiTraitStrengths, $aiDescriptions];
    }
}
