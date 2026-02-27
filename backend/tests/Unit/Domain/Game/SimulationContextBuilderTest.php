<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Game;

use App\Domain\Game\Enum\GameEventType;
use App\Domain\Game\Game;
use App\Domain\Game\GameEvent;
use App\Domain\Game\GameStatus;
use App\Domain\Game\SimulationContextBuilder;
use App\Domain\Player\Player;
use App\Domain\Player\Trait\PlayerTrait;
use App\Domain\Relationship\Relationship;
use App\Domain\TraitDef\TraitDef;
use App\Domain\TraitDef\TraitType;
use App\Domain\User\User;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class SimulationContextBuilderTest extends TestCase
{
    public function testBuildPlayerInputsCreatesCorrectInputs(): void
    {
        $game = $this->createGame();
        $owner = $game->getOwner();
        $players = $this->createPlayers($game, $owner);

        $inputs = SimulationContextBuilder::buildPlayerInputs($players);

        self::assertCount(3, $inputs);

        self::assertSame(1, $inputs[0]->getIndex());
        self::assertSame('Ondra', $inputs[0]->getName());
        self::assertTrue($inputs[0]->isHuman());

        self::assertSame(2, $inputs[1]->getIndex());
        self::assertSame('Alex', $inputs[1]->getName());
        self::assertFalse($inputs[1]->isHuman());

        self::assertSame(3, $inputs[2]->getIndex());
        self::assertSame('Bara', $inputs[2]->getName());
        self::assertFalse($inputs[2]->isHuman());
    }

    public function testBuildPlayerInputsIncludesTraitStrengths(): void
    {
        $game = $this->createGame();
        $owner = $game->getOwner();
        $player = new Player('Ondra', $game, $owner);
        $player->setDescription('Statečný vůdce');

        $traitDef = new TraitDef('leadership', 'Leadership', 'Leading', TraitType::Social);
        $playerTrait = new PlayerTrait($player, $traitDef, '0.90');
        $player->addPlayerTrait($playerTrait);

        $inputs = SimulationContextBuilder::buildPlayerInputs([$player, new Player('Alex', $game)]);

        self::assertArrayHasKey('leadership', $inputs[0]->getTraitStrengths());
        self::assertSame('0.90', $inputs[0]->getTraitStrengths()['leadership']);
    }

    public function testBuildRelationshipInputsMapsCorrectIndices(): void
    {
        $game = $this->createGame();
        $owner = $game->getOwner();
        $players = $this->createPlayers($game, $owner);
        $now = new DateTimeImmutable();

        $relationship = new Relationship($players[0], $players[1], 55, 60, 45, 30, $now);

        $inputs = SimulationContextBuilder::buildRelationshipInputs([$relationship], $players);

        self::assertCount(1, $inputs);
        self::assertSame(1, $inputs[0]->getSourceIndex());
        self::assertSame(2, $inputs[0]->getTargetIndex());
        self::assertSame(55, $inputs[0]->getTrust());
        self::assertSame(60, $inputs[0]->getAffinity());
        self::assertSame(45, $inputs[0]->getRespect());
        self::assertSame(30, $inputs[0]->getThreat());
    }

    public function testBuildEventInputsCreatesCorrectInputs(): void
    {
        $game = $this->createGame();
        $game->start(new DateTimeImmutable());
        $owner = $game->getOwner();
        $player = new Player('Ondra', $game, $owner);
        $now = new DateTimeImmutable();

        $events = [
            new GameEvent($game, GameEventType::GameStarted, 1, 6, 0, $now, null, 'Hra začala.'),
            new GameEvent($game, GameEventType::PlayerAction, 1, 6, 0, $now, $player, null, ['action_text' => 'Jdu se projít.']),
        ];

        $inputs = SimulationContextBuilder::buildEventInputs($events, [$player]);

        self::assertCount(2, $inputs);

        self::assertSame(1, $inputs[0]->getDay());
        self::assertSame(6, $inputs[0]->getHour());
        self::assertSame('game_started', $inputs[0]->getType());
        self::assertSame('Hra začala.', $inputs[0]->getNarrative());
        self::assertNull($inputs[0]->getActionText());

        self::assertSame('player_action', $inputs[1]->getType());
        self::assertSame('Ondra', $inputs[1]->getPlayerName());
        self::assertSame('Jdu se projít.', $inputs[1]->getActionText());
    }

    public function testBuildEventInputsHandlesTickSimulationEvent(): void
    {
        $game = $this->createGame();
        $game->start(new DateTimeImmutable());
        $now = new DateTimeImmutable();

        $events = [
            new GameEvent($game, GameEventType::TickSimulation, 1, 8, 1, $now, null, 'Hráči diskutovali u ohně.'),
        ];

        $inputs = SimulationContextBuilder::buildEventInputs($events, []);

        self::assertCount(1, $inputs);
        self::assertSame('tick_simulation', $inputs[0]->getType());
        self::assertSame('Hráči diskutovali u ohně.', $inputs[0]->getNarrative());
        self::assertNull($inputs[0]->getPlayerName());
    }

    public function testFindHumanPlayerIndexReturnsCorrectIndex(): void
    {
        $game = $this->createGame();
        $owner = $game->getOwner();
        $players = $this->createPlayers($game, $owner);

        $index = SimulationContextBuilder::findHumanPlayerIndex($players);

        self::assertSame(1, $index);
    }

    public function testFindHumanPlayerIndexWithHumanNotFirstReturnsCorrectIndex(): void
    {
        $game = $this->createGame();
        $owner = $game->getOwner();

        $players = [
            new Player('Alex', $game),
            new Player('Ondra', $game, $owner),
            new Player('Bara', $game),
        ];

        $index = SimulationContextBuilder::findHumanPlayerIndex($players);

        self::assertSame(2, $index);
    }

    public function testFindHumanPlayerIndexWithNoHumanReturns1(): void
    {
        $game = $this->createGame();

        $players = [
            new Player('Alex', $game),
            new Player('Bara', $game),
        ];

        $index = SimulationContextBuilder::findHumanPlayerIndex($players);

        self::assertSame(1, $index);
    }

    private function createGame(): Game
    {
        $owner = new User('owner@example.com');

        return new Game($owner, GameStatus::Setup, new DateTimeImmutable());
    }

    /**
     * @return array<int, Player>
     */
    private function createPlayers(Game $game, User $owner): array
    {
        $human = new Player('Ondra', $game, $owner);
        $human->setDescription('Statečný vůdce');

        $alex = new Player('Alex', $game);
        $alex->setDescription('Tichý stratég');

        $bara = new Player('Bara', $game);
        $bara->setDescription('Empatická pozorovatelka');

        return [$human, $alex, $bara];
    }
}
