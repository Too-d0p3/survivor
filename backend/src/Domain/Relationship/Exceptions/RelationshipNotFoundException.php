<?php

declare(strict_types=1);

namespace App\Domain\Relationship\Exceptions;

use RuntimeException;
use Symfony\Component\Uid\Uuid;
use Throwable;

final class RelationshipNotFoundException extends RuntimeException
{
    private readonly Uuid $relationshipId;

    public function __construct(
        Uuid $relationshipId,
        ?Throwable $previous = null,
    ) {
        $this->relationshipId = $relationshipId;

        parent::__construct(
            sprintf('Relationship with id `%s` not found', $relationshipId->toString()),
            0,
            $previous,
        );
    }

    public function getRelationshipId(): Uuid
    {
        return $this->relationshipId;
    }
}
