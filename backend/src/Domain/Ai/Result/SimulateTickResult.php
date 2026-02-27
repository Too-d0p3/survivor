<?php

declare(strict_types=1);

namespace App\Domain\Ai\Result;

final readonly class SimulateTickResult
{
    private string $reasoning;

    private string $playerLocation;

    /** @var array<int, int> */
    private array $playersNearby;

    private string $macroNarrative;

    private string $playerNarrative;

    /** @var array<int, RelationshipDelta> */
    private array $relationshipChanges;

    /**
     * @param array<int, int> $playersNearby
     * @param array<int, RelationshipDelta> $relationshipChanges
     */
    public function __construct(
        string $reasoning,
        string $playerLocation,
        array $playersNearby,
        string $macroNarrative,
        string $playerNarrative,
        array $relationshipChanges,
    ) {
        $this->reasoning = $reasoning;
        $this->playerLocation = $playerLocation;
        $this->playersNearby = $playersNearby;
        $this->macroNarrative = $macroNarrative;
        $this->playerNarrative = $playerNarrative;
        $this->relationshipChanges = $relationshipChanges;
    }

    public function getReasoning(): string
    {
        return $this->reasoning;
    }

    public function getPlayerLocation(): string
    {
        return $this->playerLocation;
    }

    /**
     * @return array<int, int>
     */
    public function getPlayersNearby(): array
    {
        return $this->playersNearby;
    }

    public function getMacroNarrative(): string
    {
        return $this->macroNarrative;
    }

    public function getPlayerNarrative(): string
    {
        return $this->playerNarrative;
    }

    /**
     * @return array<int, RelationshipDelta>
     */
    public function getRelationshipChanges(): array
    {
        return $this->relationshipChanges;
    }
}
