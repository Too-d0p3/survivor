<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\User;

use App\Domain\Game\Game;
use App\Domain\User\User;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    public function testConstructorSetsEmailAndGeneratesUuid(): void
    {
        $user = new User('test@example.com');

        self::assertSame('test@example.com', $user->getEmail());
        self::assertNotEmpty($user->getId()->toRfc4122());
    }

    public function testGetRolesAlwaysIncludesRoleUser(): void
    {
        $user = new User('test@example.com');

        $roles = $user->getRoles();

        self::assertContains('ROLE_USER', $roles);
    }

    public function testSetPasswordUpdatesPassword(): void
    {
        $user = new User('test@example.com');

        $user->setPassword('hashed_password');

        self::assertSame('hashed_password', $user->getPassword());
    }

    public function testAddGameAddsGameToCollectionAndSetsOwner(): void
    {
        $user = new User('test@example.com');
        $game = new Game($user, false, new DateTimeImmutable());

        $user->addGame($game);

        self::assertCount(1, $user->getGames());
        self::assertSame($game, $user->getGames()[0]);
        self::assertSame($user, $game->getOwner());
    }

    public function testRemoveGameRemovesFromCollection(): void
    {
        $user = new User('test@example.com');
        $game = new Game($user, false, new DateTimeImmutable());
        $user->addGame($game);

        $user->removeGame($game);

        self::assertCount(0, $user->getGames());
    }
}
