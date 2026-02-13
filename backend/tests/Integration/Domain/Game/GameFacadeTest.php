<?php

declare(strict_types=1);

namespace App\Tests\Integration\Domain\Game;

use App\Domain\Game\Game;
use App\Domain\Game\GameFacade;
use App\Tests\Integration\AbstractIntegrationTestCase;
use DateTimeImmutable;

final class GameFacadeTest extends AbstractIntegrationTestCase
{
    private GameFacade $gameFacade;

    public function testCreateGameForUserPersistsGame(): void
    {
        $user = $this->createAndPersistUser();

        $game = $this->gameFacade->createGameForUser($user);

        $foundGame = $this->getEntityManager()->find(Game::class, $game->getId());
        self::assertNotNull($foundGame);
    }

    public function testCreateGameForUserSetsCorrectOwner(): void
    {
        $user = $this->createAndPersistUser();

        $game = $this->gameFacade->createGameForUser($user);

        self::assertSame($user, $game->getOwner());
    }

    public function testCreateGameForUserSetsCreatedAt(): void
    {
        $user = $this->createAndPersistUser();
        $before = new DateTimeImmutable();

        $game = $this->gameFacade->createGameForUser($user);

        $after = new DateTimeImmutable();
        self::assertGreaterThanOrEqual($before, $game->getCreatedAt());
        self::assertLessThanOrEqual($after, $game->getCreatedAt());
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->gameFacade = $this->getService(GameFacade::class);
    }
}
