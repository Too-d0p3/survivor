<?php

declare(strict_types=1);

namespace App\Domain\Game;

use App\Domain\Ai\Operation\SimulationEventInput;
use App\Domain\Ai\Operation\SimulationPlayerInput;
use App\Domain\Ai\Operation\SimulationRelationshipInput;
use App\Domain\Game\Enum\GameEventType;
use App\Domain\Player\Player;
use App\Domain\Relationship\Relationship;

final class SimulationContextBuilder
{
    /**
     * @param array<int, Player> $players
     * @return array<int, SimulationPlayerInput>
     */
    public static function buildPlayerInputs(array $players): array
    {
        $inputs = [];

        foreach (array_values($players) as $i => $player) {
            $traitStrengths = [];

            foreach ($player->getPlayerTraits() as $playerTrait) {
                $traitStrengths[$playerTrait->getTraitDef()->getKey()] = $playerTrait->getStrength();
            }

            $inputs[] = new SimulationPlayerInput(
                $i + 1,
                $player->getName(),
                $player->getDescription() ?? '',
                $traitStrengths,
                $player->isHuman(),
            );
        }

        return $inputs;
    }

    /**
     * @param array<int, Relationship> $relationships
     * @param array<int, Player> $players
     * @return array<int, SimulationRelationshipInput>
     */
    public static function buildRelationshipInputs(array $relationships, array $players): array
    {
        // Build player ID to 1-based index map
        $playerIndexMap = [];
        foreach (array_values($players) as $i => $player) {
            $playerIndexMap[$player->getId()->toString()] = $i + 1;
        }

        $inputs = [];

        foreach ($relationships as $relationship) {
            $sourceId = $relationship->getSource()->getId()->toString();
            $targetId = $relationship->getTarget()->getId()->toString();

            $sourceIndex = $playerIndexMap[$sourceId] ?? null;
            $targetIndex = $playerIndexMap[$targetId] ?? null;

            if ($sourceIndex === null || $targetIndex === null) {
                continue;
            }

            $inputs[] = new SimulationRelationshipInput(
                $sourceIndex,
                $targetIndex,
                $relationship->getTrust(),
                $relationship->getAffinity(),
                $relationship->getRespect(),
                $relationship->getThreat(),
            );
        }

        return $inputs;
    }

    /**
     * @param array<int, GameEvent> $events
     * @param array<int, Player> $players
     * @return array<int, SimulationEventInput>
     */
    public static function buildEventInputs(array $events, array $players): array
    {
        // Build player ID to name map
        $playerNameMap = [];
        foreach ($players as $player) {
            $playerNameMap[$player->getId()->toString()] = $player->getName();
        }

        $inputs = [];

        foreach ($events as $event) {
            $playerName = null;
            $narrative = $event->getNarrative();
            $actionText = null;

            if ($event->getPlayer() !== null) {
                $playerName = $playerNameMap[$event->getPlayer()->getId()->toString()] ?? null;
            }

            $metadata = $event->getMetadata();

            if ($event->getType() === GameEventType::PlayerAction && is_array($metadata) && isset($metadata['action_text'])) {
                /** @var string $actionText */
                $actionText = $metadata['action_text'];
            }

            $inputs[] = new SimulationEventInput(
                $event->getDay(),
                $event->getHour(),
                $event->getType()->value,
                $playerName,
                $narrative,
                $actionText,
            );
        }

        return $inputs;
    }

    /**
     * @param array<int, Player> $players
     */
    public static function findHumanPlayerIndex(array $players): int
    {
        foreach (array_values($players) as $i => $player) {
            if ($player->isHuman()) {
                return $i + 1;
            }
        }

        return 1;
    }
}
