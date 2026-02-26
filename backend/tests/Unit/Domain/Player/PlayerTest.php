<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Player;

use App\Domain\Game\Game;
use App\Domain\Game\GameStatus;
use App\Domain\Player\Player;
use App\Domain\Player\Trait\PlayerTrait;
use App\Domain\TraitDef\TraitDef;
use App\Domain\TraitDef\TraitType;
use App\Domain\User\User;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class PlayerTest extends TestCase
{
    public function testConstructorWithUserSetsHumanPlayer(): void
    {
        $user = new User('owner@example.com');
        $game = new Game($user, GameStatus::Setup, new DateTimeImmutable());

        $player = new Player('Alice', $game, $user);

        self::assertNotEmpty($player->getId()->toRfc4122());
        self::assertSame('Alice', $player->getName());
        self::assertTrue($player->isHuman());
        self::assertSame($user, $player->getUser());
        self::assertSame($game, $player->getGame());
        self::assertNull($player->getDescription());
        self::assertSame([], $player->getPlayerTraits());
    }

    public function testConstructorWithoutUserSetsAiPlayer(): void
    {
        $user = new User('owner@example.com');
        $game = new Game($user, GameStatus::Setup, new DateTimeImmutable());

        $player = new Player('Bot', $game);

        self::assertFalse($player->isHuman());
        self::assertNull($player->getUser());
    }

    public function testSetDescriptionSetsDescription(): void
    {
        $user = new User('owner@example.com');
        $game = new Game($user, GameStatus::Setup, new DateTimeImmutable());
        $player = new Player('Alice', $game);

        $player->setDescription('A strategic player');

        self::assertSame('A strategic player', $player->getDescription());
    }

    public function testAddPlayerTraitAddsAndSetsPlayer(): void
    {
        $user = new User('owner@example.com');
        $game = new Game($user, GameStatus::Setup, new DateTimeImmutable());
        $player = new Player('Alice', $game);
        $traitDef = new TraitDef('charisma', 'Charisma', 'Social charm', TraitType::Social);
        $playerTrait = new PlayerTrait($player, $traitDef, '0.75');

        $player->addPlayerTrait($playerTrait);

        self::assertCount(1, $player->getPlayerTraits());
        self::assertSame($playerTrait, $player->getPlayerTraits()[0]);
        self::assertSame($player, $playerTrait->getPlayer());
    }

    public function testAddPlayerTraitDoesNotDuplicate(): void
    {
        $user = new User('owner@example.com');
        $game = new Game($user, GameStatus::Setup, new DateTimeImmutable());
        $player = new Player('Alice', $game);
        $traitDef = new TraitDef('charisma', 'Charisma', 'Social charm', TraitType::Social);
        $playerTrait = new PlayerTrait($player, $traitDef, '0.75');

        $player->addPlayerTrait($playerTrait);
        $player->addPlayerTrait($playerTrait);

        self::assertCount(1, $player->getPlayerTraits());
    }

    public function testRemovePlayerTraitRemoves(): void
    {
        $user = new User('owner@example.com');
        $game = new Game($user, GameStatus::Setup, new DateTimeImmutable());
        $player = new Player('Alice', $game);
        $traitDef = new TraitDef('charisma', 'Charisma', 'Social charm', TraitType::Social);
        $playerTrait = new PlayerTrait($player, $traitDef, '0.75');
        $player->addPlayerTrait($playerTrait);

        $player->removePlayerTrait($playerTrait);

        self::assertCount(0, $player->getPlayerTraits());
    }
}
