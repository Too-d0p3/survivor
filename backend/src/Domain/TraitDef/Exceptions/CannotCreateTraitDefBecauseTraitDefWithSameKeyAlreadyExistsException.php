<?php

declare(strict_types=1);

namespace App\Domain\TraitDef\Exceptions;

use App\Domain\TraitDef\TraitDef;
use RuntimeException;
use Throwable;

class CannotCreateTraitDefBecauseTraitDefWithSameKeyAlreadyExistsException extends RuntimeException
{
    private readonly string $key;

    private readonly TraitDef $existingTraitDef;

    public function __construct(
        string $key,
        TraitDef $existingTraitDef,
        ?Throwable $previous = null,
    ) {
        $this->key = $key;
        $this->existingTraitDef = $existingTraitDef;

        parent::__construct(
            sprintf(
                'Cannot create trait definition with key `%s`'
                . ' because trait definition `%s` with same key already exists',
                $key,
                $this->existingTraitDef->getId(),
            ),
            0,
            $previous,
        );
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getExistingTraitDef(): TraitDef
    {
        return $this->existingTraitDef;
    }
}
