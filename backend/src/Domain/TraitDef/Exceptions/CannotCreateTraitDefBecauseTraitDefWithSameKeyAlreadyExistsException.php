<?php

namespace App\Domain\TraitDef\Exceptions;

use App\Domain\TraitDef\TraitDef;
use RuntimeException;
use Throwable;

class CannotCreateTraitDefBecauseTraitDefWithSameKeyAlreadyExistsException extends RuntimeException
{

    public function __construct(
        private string $key,
        private TraitDef $existingTraitDef,
        ?Throwable $previous = null
    ) {
        parent::__construct(sprintf(
            'Cannot create trait definition with key `%s` because trait definition `%i` with same key already exist',
            $key,
            $this->existingTraitDef->getId(),
        ), $previous);
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