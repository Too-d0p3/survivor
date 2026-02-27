<?php

declare(strict_types=1);

namespace App\Domain\Game;

use App\Domain\Ai\Result\RelationshipDelta;
use App\Domain\Ai\Result\SimulateTickResult;
use App\Domain\Game\Enum\GameEventType;
use App\Domain\Game\Result\ApplySimulationResult;
use App\Domain\Player\Player;
use App\Domain\Relationship\Relationship;
use DateTimeImmutable;

final class SimulationService
{
    private const int MAX_DELTA = 15;

    /**
     * @param array<int, Player> $players 0-indexed array of players (same order as in SimulationContextBuilder)
     * @param array<int, Relationship> $relationships All relationships for the game
     */
    public function applySimulation(
        Game $game,
        SimulateTickResult $simulationResult,
        array $players,
        array $relationships,
        int $day,
        int $hour,
        int $tick,
        DateTimeImmutable $now,
    ): ApplySimulationResult {
        $events = [];

        // Create TickSimulation event (game-level, no player)
        $tickSimulationEvent = new GameEvent(
            $game,
            GameEventType::TickSimulation,
            $day,
            $hour,
            $tick,
            $now,
            null,
            $simulationResult->getMacroNarrative(),
            [
                'reasoning' => $simulationResult->getReasoning(),
                'player_location' => $simulationResult->getPlayerLocation(),
                'players_nearby' => $simulationResult->getPlayersNearby(),
                'relationship_changes' => $this->serializeRelationshipChanges($simulationResult->getRelationshipChanges()),
            ],
        );
        $events[] = $tickSimulationEvent;

        // Create PlayerPerspective event (for human player)
        $humanPlayer = $this->findHumanPlayer($players);

        if ($humanPlayer !== null) {
            $playerPerspectiveEvent = new GameEvent(
                $game,
                GameEventType::PlayerPerspective,
                $day,
                $hour,
                $tick,
                $now,
                $humanPlayer,
                $simulationResult->getPlayerNarrative(),
                [
                    'player_location' => $simulationResult->getPlayerLocation(),
                    'players_nearby' => $simulationResult->getPlayersNearby(),
                ],
            );
            $events[] = $playerPerspectiveEvent;
        }

        // Apply relationship changes
        $this->applyRelationshipChanges(
            $simulationResult->getRelationshipChanges(),
            $players,
            $relationships,
            $now,
        );

        return new ApplySimulationResult($events);
    }

    /**
     * @param array<int, RelationshipDelta> $changes
     * @param array<int, Player> $players 0-indexed
     * @param array<int, Relationship> $relationships
     */
    private function applyRelationshipChanges(
        array $changes,
        array $players,
        array $relationships,
        DateTimeImmutable $now,
    ): void {
        // Build a lookup: "sourcePlayerId:targetPlayerId" => Relationship
        $relationshipMap = [];
        foreach ($relationships as $relationship) {
            $key = $relationship->getSource()->getId()->toString() . ':' . $relationship->getTarget()->getId()->toString();
            $relationshipMap[$key] = $relationship;
        }

        // Build player index to Player lookup (1-indexed to 0-indexed)
        $playerByIndex = [];
        foreach (array_values($players) as $i => $player) {
            $playerByIndex[$i + 1] = $player;
        }

        foreach ($changes as $delta) {
            $sourcePlayer = $playerByIndex[$delta->sourceIndex] ?? null;
            $targetPlayer = $playerByIndex[$delta->targetIndex] ?? null;

            if ($sourcePlayer === null || $targetPlayer === null) {
                continue;
            }

            $key = $sourcePlayer->getId()->toString() . ':' . $targetPlayer->getId()->toString();
            $relationship = $relationshipMap[$key] ?? null;

            if ($relationship === null) {
                continue;
            }

            $relationship->adjustTrust($this->clampDelta($delta->trustDelta), $now);
            $relationship->adjustAffinity($this->clampDelta($delta->affinityDelta), $now);
            $relationship->adjustRespect($this->clampDelta($delta->respectDelta), $now);
            $relationship->adjustThreat($this->clampDelta($delta->threatDelta), $now);
        }
    }

    private function clampDelta(int $delta): int
    {
        return max(-self::MAX_DELTA, min(self::MAX_DELTA, $delta));
    }

    /**
     * @param array<int, Player> $players
     */
    private function findHumanPlayer(array $players): ?Player
    {
        foreach ($players as $player) {
            if ($player->isHuman()) {
                return $player;
            }
        }

        return null;
    }

    /**
     * @param array<int, RelationshipDelta> $changes
     * @return array<int, array<string, int>>
     */
    private function serializeRelationshipChanges(array $changes): array
    {
        $serialized = [];

        foreach ($changes as $change) {
            $serialized[] = [
                'source_index' => $change->sourceIndex,
                'target_index' => $change->targetIndex,
                'trust_delta' => $change->trustDelta,
                'affinity_delta' => $change->affinityDelta,
                'respect_delta' => $change->respectDelta,
                'threat_delta' => $change->threatDelta,
            ];
        }

        return $serialized;
    }
}
