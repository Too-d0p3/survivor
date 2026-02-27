<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Game;

use App\Domain\Game\Exceptions\CannotAdvanceTickBecauseGameIsNotInProgressException;
use App\Domain\Game\Exceptions\CannotStartGameBecauseGameIsNotInSetupException;
use App\Domain\Game\Game;
use App\Domain\Game\GameStatus;
use App\Domain\Player\Player;
use App\Domain\User\User;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class GameTest extends TestCase
{
    public function testConstructorSetsAllProperties(): void
    {
        $user = new User('owner@example.com');
        $createdAt = new DateTimeImmutable('2024-01-01 12:00:00');

        $game = new Game($user, GameStatus::Setup, $createdAt);

        self::assertNotEmpty($game->getId()->toRfc4122());
        self::assertSame($user, $game->getOwner());
        self::assertSame(GameStatus::Setup, $game->getStatus());
        self::assertSame($createdAt, $game->getCreatedAt());
        self::assertSame([], $game->getPlayers());
    }

    public function testAddPlayerAddsToCollectionAndSetsGame(): void
    {
        $user = new User('owner@example.com');
        $game = new Game($user, GameStatus::Setup, new DateTimeImmutable());
        $player = new Player('Alice', $game);

        $game->addPlayer($player);

        self::assertCount(1, $game->getPlayers());
        self::assertSame($player, $game->getPlayers()[0]);
        self::assertSame($game, $player->getGame());
    }

    public function testAddPlayerDoesNotDuplicate(): void
    {
        $user = new User('owner@example.com');
        $game = new Game($user, GameStatus::Setup, new DateTimeImmutable());
        $player = new Player('Alice', $game);

        $game->addPlayer($player);
        $game->addPlayer($player);

        self::assertCount(1, $game->getPlayers());
    }

    public function testRemovePlayerRemovesFromCollection(): void
    {
        $user = new User('owner@example.com');
        $game = new Game($user, GameStatus::Setup, new DateTimeImmutable());
        $player = new Player('Alice', $game);
        $game->addPlayer($player);

        $game->removePlayer($player);

        self::assertCount(0, $game->getPlayers());
    }

    public function testStartSetsStatusToInProgress(): void
    {
        $game = $this->createSetupGame();
        $now = new DateTimeImmutable('2026-01-01 12:00:00');

        $game->start($now);

        self::assertSame(GameStatus::InProgress, $game->getStatus());
    }

    public function testStartSetsInitialTimeFields(): void
    {
        $game = $this->createSetupGame();
        $now = new DateTimeImmutable('2026-01-01 12:00:00');

        $game->start($now);

        self::assertSame(1, $game->getCurrentDay());
        self::assertSame(6, $game->getCurrentHour());
        self::assertSame(0, $game->getCurrentTick());
    }

    public function testStartSetsStartedAt(): void
    {
        $game = $this->createSetupGame();
        $now = new DateTimeImmutable('2026-01-01 12:00:00');

        $game->start($now);

        self::assertSame($now, $game->getStartedAt());
    }

    public function testStartWhenNotInSetupThrowsException(): void
    {
        $game = $this->createSetupGame();
        $now = new DateTimeImmutable();
        $game->start($now);

        $this->expectException(CannotStartGameBecauseGameIsNotInSetupException::class);

        $game->start($now);
    }

    public function testAdvanceTickIncrementsTickAndHour(): void
    {
        $game = $this->createInProgressGame();

        $game->advanceTick();

        self::assertSame(1, $game->getCurrentTick());
        self::assertSame(8, $game->getCurrentHour());
    }

    public function testAdvanceTickWhenNotInProgressThrowsException(): void
    {
        $game = $this->createSetupGame();

        $this->expectException(CannotAdvanceTickBecauseGameIsNotInProgressException::class);

        $game->advanceTick();
    }

    public function testSleepToNextDayIncrementsDayAndResetsHour(): void
    {
        $game = $this->createInProgressGame();

        $game->sleepToNextDay();

        self::assertSame(2, $game->getCurrentDay());
        self::assertSame(6, $game->getCurrentHour());
    }

    public function testSleepToNextDayWhenNotInProgressThrowsException(): void
    {
        $game = $this->createSetupGame();

        $this->expectException(CannotAdvanceTickBecauseGameIsNotInProgressException::class);

        $game->sleepToNextDay();
    }

    private function createSetupGame(): Game
    {
        $user = new User('owner@example.com');

        return new Game($user, GameStatus::Setup, new DateTimeImmutable());
    }

    private function createInProgressGame(): Game
    {
        $game = $this->createSetupGame();
        $game->start(new DateTimeImmutable());

        return $game;
    }
}
