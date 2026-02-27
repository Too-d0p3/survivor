<?php

declare(strict_types=1);

namespace App\Domain\Ai\Result;

final readonly class InitializeRelationshipsResult
{
    /** @var array<int, RelationshipValues> */
    private array $relationships;

    /**
     * @param array<int, RelationshipValues> $relationships
     */
    public function __construct(array $relationships)
    {
        $this->relationships = $relationships;
    }

    /**
     * @return array<int, RelationshipValues>
     */
    public function getRelationships(): array
    {
        return $this->relationships;
    }
}
