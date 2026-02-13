<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Game;

use App\Domain\Game\Exceptions\CannotDeleteGameBecauseUserIsNotOwnerException;
use App\Domain\Game\Game;
use App\Domain\Game\GameService;
use App\Domain\User\User;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class GameServiceTest extends TestCase
{
    private GameService $gameService;

    public function testDeleteGameWhenUserIsOwnerReturnsGame(): void
    {
        $owner = new User('owner@example.com');
        $game = new Game($owner, false, new DateTimeImmutable());

        $result = $this->gameService->deleteGame($game, $owner);

        self::assertSame($game, $result);
    }

    public function testDeleteGameWhenUserIsNotOwnerThrowsException(): void
    {
        $owner = new User('owner@example.com');
        $otherUser = new User('other@example.com');
        $game = new Game($owner, false, new DateTimeImmutable());

        $this->expectException(CannotDeleteGameBecauseUserIsNotOwnerException::class);

        $this->gameService->deleteGame($game, $otherUser);
    }

    protected function setUp(): void
    {
        $this->gameService = new GameService();
    }
}
