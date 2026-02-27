<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Game;

use App\Domain\Game\Enum\GameEventType;
use App\Domain\Game\Game;
use App\Domain\Game\GameEvent;
use App\Domain\Game\GameStatus;
use App\Domain\Player\Player;
use App\Domain\User\User;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class GameEventTest extends TestCase
{
    public function testConstructorSetsAllProperties(): void
    {
        $user = new User('owner@example.com');
        $game = new Game($user, GameStatus::InProgress, new DateTimeImmutable());
        $player = new Player('Alice', $game, $user);
        $createdAt = new DateTimeImmutable('2026-01-01 12:00:00');

        $event = new GameEvent(
            $game,
            GameEventType::PlayerAction,
            1,
            6,
            0,
            $createdAt,
            $player,
            'Something happened',
            ['action_text' => 'Went fishing'],
        );

        self::assertNotEmpty($event->getId()->toRfc4122());
        self::assertSame($game, $event->getGame());
        self::assertSame(GameEventType::PlayerAction, $event->getType());
        self::assertSame(1, $event->getDay());
        self::assertSame(6, $event->getHour());
        self::assertSame(0, $event->getTick());
        self::assertSame($createdAt, $event->getCreatedAt());
        self::assertSame($player, $event->getPlayer());
        self::assertSame('Something happened', $event->getNarrative());
        self::assertSame(['action_text' => 'Went fishing'], $event->getMetadata());
    }

    public function testConstructorWithNullOptionalFields(): void
    {
        $user = new User('owner@example.com');
        $game = new Game($user, GameStatus::InProgress, new DateTimeImmutable());
        $createdAt = new DateTimeImmutable('2026-01-01 12:00:00');

        $event = new GameEvent(
            $game,
            GameEventType::GameStarted,
            1,
            6,
            0,
            $createdAt,
        );

        self::assertNull($event->getPlayer());
        self::assertNull($event->getNarrative());
        self::assertNull($event->getMetadata());
    }
}
