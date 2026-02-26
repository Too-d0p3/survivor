<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Game;

use App\Domain\Game\Exceptions\CannotDeleteGameBecauseUserIsNotOwnerException;
use App\Domain\Game\Game;
use App\Domain\Game\GameService;
use App\Domain\Game\GameStatus;
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
