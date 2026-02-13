<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Player;

use App\Domain\Game\Game;
use App\Domain\Player\Player;
use App\Domain\Player\Trait\PlayerTrait;
use App\Domain\TraitDef\TraitDef;
use App\Domain\TraitDef\TraitType;
use App\Domain\User\User;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class PlayerTest extends TestCase
{
    public function testConstructorSetsAllProperties(): void
    {
        $user = new User('owner@example.com');
        $game = new Game($user, false, new DateTimeImmutable());

        $player = new Player('Alice', true, $game);

        self::assertNotEmpty($player->getId()->toRfc4122());
        self::assertSame('Alice', $player->getName());
        self::assertTrue($player->isUserControlled());
        self::assertSame($game, $player->getGame());
        self::assertNull($player->getDescription());
        self::assertSame([], $player->getPlayerTraits());
    }

    public function testAddPlayerTraitAddsAndSetsPlayer(): void
    {
        $user = new User('owner@example.com');
        $game = new Game($user, false, new DateTimeImmutable());
        $player = new Player('Alice', false, $game);
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
        $game = new Game($user, false, new DateTimeImmutable());
        $player = new Player('Alice', false, $game);
        $traitDef = new TraitDef('charisma', 'Charisma', 'Social charm', TraitType::Social);
        $playerTrait = new PlayerTrait($player, $traitDef, '0.75');

        $player->addPlayerTrait($playerTrait);
        $player->addPlayerTrait($playerTrait);

        self::assertCount(1, $player->getPlayerTraits());
    }

    public function testRemovePlayerTraitRemoves(): void
    {
        $user = new User('owner@example.com');
        $game = new Game($user, false, new DateTimeImmutable());
        $player = new Player('Alice', false, $game);
        $traitDef = new TraitDef('charisma', 'Charisma', 'Social charm', TraitType::Social);
        $playerTrait = new PlayerTrait($player, $traitDef, '0.75');
        $player->addPlayerTrait($playerTrait);

        $player->removePlayerTrait($playerTrait);

        self::assertCount(0, $player->getPlayerTraits());
    }
}
