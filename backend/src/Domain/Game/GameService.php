<?php

declare(strict_types=1);

namespace App\Domain\Game;

use App\Domain\Game\Exceptions\CannotDeleteGameBecauseUserIsNotOwnerException;
use App\Domain\Game\Result\CreateGameResult;
use App\Domain\Player\Player;
use App\Domain\Player\Trait\PlayerTrait;
use App\Domain\TraitDef\TraitDef;
use App\Domain\User\User;
use DateTimeImmutable;

final class GameService
{
    private const array AI_PLAYER_NAMES = ['Alex', 'Bara', 'Cyril', 'Dana', 'Emil'];

    /**
     * @param array<string, string> $humanTraitStrengths
     * @param array<int, TraitDef> $traitDefs
     * @param array<int, array<string, string>> $aiPlayerTraitStrengths
     * @param array<int, string> $aiPlayerDescriptions
     */
    public function createGame(
        User $owner,
        string $humanPlayerName,
        string $humanPlayerDescription,
        array $humanTraitStrengths,
        array $traitDefs,
        array $aiPlayerTraitStrengths,
        array $aiPlayerDescriptions,
        DateTimeImmutable $now,
    ): CreateGameResult {
        $game = new Game($owner, GameStatus::Setup, $now);

        $traitDefsByKey = $this->indexTraitDefsByKey($traitDefs);

        $humanPlayer = new Player($humanPlayerName, $game, $owner);
        $humanPlayer->setDescription($humanPlayerDescription);
        $this->assignTraits($humanPlayer, $humanTraitStrengths, $traitDefsByKey);
        $game->addPlayer($humanPlayer);

        foreach (self::AI_PLAYER_NAMES as $index => $aiName) {
            $aiPlayer = new Player($aiName, $game);
            $aiPlayer->setDescription($aiPlayerDescriptions[$index]);
            $this->assignTraits($aiPlayer, $aiPlayerTraitStrengths[$index], $traitDefsByKey);
            $game->addPlayer($aiPlayer);
        }

        return new CreateGameResult($game);
    }

    /**
     * @throws CannotDeleteGameBecauseUserIsNotOwnerException
     */
    public function deleteGame(Game $game, User $requestingUser): Game
    {
        if ($game->getOwner() !== $requestingUser) {
            throw new CannotDeleteGameBecauseUserIsNotOwnerException($game, $requestingUser);
        }

        return $game;
    }

    /**
     * @param array<string, string> $traitStrengths
     * @param array<string, TraitDef> $traitDefsByKey
     */
    private function assignTraits(Player $player, array $traitStrengths, array $traitDefsByKey): void
    {
        foreach ($traitStrengths as $key => $strength) {
            if (!isset($traitDefsByKey[$key])) {
                continue;
            }

            $playerTrait = new PlayerTrait($player, $traitDefsByKey[$key], $strength);
            $player->addPlayerTrait($playerTrait);
        }
    }

    /**
     * @param array<int, TraitDef> $traitDefs
     * @return array<string, TraitDef>
     */
    private function indexTraitDefsByKey(array $traitDefs): array
    {
        $indexed = [];

        foreach ($traitDefs as $traitDef) {
            $indexed[$traitDef->getKey()] = $traitDef;
        }

        return $indexed;
    }
}
