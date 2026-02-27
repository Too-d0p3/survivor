<?php

declare(strict_types=1);

namespace App\Domain\Relationship;

use App\Domain\Ai\Result\InitializeRelationshipsResult;
use App\Domain\Player\Player;
use DateTimeImmutable;

final class RelationshipService
{
    /**
     * Creates Relationship entities from AI-generated values.
     *
     * @param array<int, Player> $players 0-indexed array of all players in the game
     * @return array<int, Relationship>
     */
    public function initializeRelationships(
        array $players,
        InitializeRelationshipsResult $aiResult,
        DateTimeImmutable $now,
    ): array {
        $playerByIndex = [];
        foreach (array_values($players) as $index => $player) {
            $playerByIndex[$index + 1] = $player;
        }

        $relationships = [];

        foreach ($aiResult->getRelationships() as $scores) {
            $source = $playerByIndex[$scores->sourceIndex];
            $target = $playerByIndex[$scores->targetIndex];

            $relationships[] = new Relationship(
                $source,
                $target,
                $scores->trust,
                $scores->affinity,
                $scores->respect,
                $scores->threat,
                $now,
            );
        }

        return $relationships;
    }
}
