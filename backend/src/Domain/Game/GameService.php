<?php

declare(strict_types=1);

namespace App\Domain\Game;

use App\Domain\Game\Enum\GameEventType;
use App\Domain\Game\Exceptions\CannotDeleteGameBecauseUserIsNotOwnerException;
use App\Domain\Game\Exceptions\CannotProcessTickBecauseGameIsNotInProgressException;
use App\Domain\Game\Exceptions\CannotStartGameBecauseUserIsNotOwnerException;
use App\Domain\Game\Result\CreateGameResult;
use App\Domain\Game\Result\ProcessTickResult;
use App\Domain\Game\Result\StartGameResult;
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
     * @throws CannotStartGameBecauseUserIsNotOwnerException
     */
    public function startGame(Game $game, User $requestingUser, DateTimeImmutable $now): StartGameResult
    {
        if ($game->getOwner() !== $requestingUser) {
            throw new CannotStartGameBecauseUserIsNotOwnerException($game, $requestingUser);
        }

        $game->start($now);

        $event = new GameEvent(
            $game,
            GameEventType::GameStarted,
            1,
            6,
            0,
            $now,
        );

        return new StartGameResult($game, [$event]);
    }

    /**
     * @throws CannotProcessTickBecauseGameIsNotInProgressException
     */
    public function processTick(Game $game, Player $humanPlayer, string $actionText, DateTimeImmutable $now): ProcessTickResult
    {
        if ($game->getStatus() !== GameStatus::InProgress) {
            throw new CannotProcessTickBecauseGameIsNotInProgressException($game);
        }

        $events = [];

        /** @var int $currentDay */
        $currentDay = $game->getCurrentDay();
        /** @var int $currentHour */
        $currentHour = $game->getCurrentHour();
        /** @var int $currentTick */
        $currentTick = $game->getCurrentTick();

        $playerActionEvent = new GameEvent(
            $game,
            GameEventType::PlayerAction,
            $currentDay,
            $currentHour,
            $currentTick,
            $now,
            $humanPlayer,
            null,
            ['action_text' => $actionText],
        );
        $events[] = $playerActionEvent;

        // [FUTURE HOOK: AI player actions]
        // [FUTURE HOOK: relationship updates]
        // [FUTURE HOOK: narrative generation]

        $game->advanceTick();

        if ($game->getCurrentHour() >= 24) {
            /** @var int $advancedDay */
            $advancedDay = $game->getCurrentDay();
            /** @var int $advancedTick */
            $advancedTick = $game->getCurrentTick();

            $nightSleepEvent = new GameEvent(
                $game,
                GameEventType::NightSleep,
                $advancedDay,
                22,
                $advancedTick,
                $now,
            );
            $events[] = $nightSleepEvent;

            $game->sleepToNextDay();
        }

        return new ProcessTickResult($game, $events);
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
