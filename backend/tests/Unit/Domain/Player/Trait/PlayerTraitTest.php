<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Player\Trait;

use App\Domain\Game\Game;
use App\Domain\Player\Player;
use App\Domain\Player\Trait\PlayerTrait;
use App\Domain\TraitDef\TraitDef;
use App\Domain\TraitDef\TraitType;
use App\Domain\User\User;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class PlayerTraitTest extends TestCase
{
    public function testConstructorSetsAllProperties(): void
    {
        $user = new User('owner@example.com');
        $game = new Game($user, false, new DateTimeImmutable());
        $player = new Player('Alice', false, $game);
        $traitDef = new TraitDef('charisma', 'Charisma', 'Social charm', TraitType::Social);

        $playerTrait = new PlayerTrait($player, $traitDef, '0.85');

        self::assertNotEmpty($playerTrait->getId()->toRfc4122());
        self::assertSame($player, $playerTrait->getPlayer());
        self::assertSame($traitDef, $playerTrait->getTraitDef());
        self::assertSame('0.85', $playerTrait->getStrength());
    }

    public function testSetPlayerChangesPlayer(): void
    {
        $user = new User('owner@example.com');
        $game = new Game($user, false, new DateTimeImmutable());
        $player1 = new Player('Alice', false, $game);
        $player2 = new Player('Bob', false, $game);
        $traitDef = new TraitDef('charisma', 'Charisma', 'Social charm', TraitType::Social);
        $playerTrait = new PlayerTrait($player1, $traitDef, '0.50');

        $playerTrait->setPlayer($player2);

        self::assertSame($player2, $playerTrait->getPlayer());
    }
}
