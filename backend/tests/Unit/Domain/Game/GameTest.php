<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Game;

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
}
