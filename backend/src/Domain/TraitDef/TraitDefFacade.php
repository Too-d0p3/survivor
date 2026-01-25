<?php

namespace App\Domain\TraitDef;

use App\Domain\TraitDef\Exceptions\CannotCreateTraitDefBecauseTraitDefWithSameKeyAlreadyExistsException;
use Doctrine\ORM\EntityManagerInterface;

class TraitDefFacade
{

    public function __construct(
        private TraitDefRepository $traitDefRepository,
        private EntityManagerInterface $entityManager,
    )
    {
    }


    public function createTraitDef(
        string $key,
        string $label,
        string $description,
        string $type,
    ): TraitDef
    {
        $existingTraitDef = $this->traitDefRepository->findOneBy(['key' => $key]);

        if ($existingTraitDef !== null) {
            throw new CannotCreateTraitDefBecauseTraitDefWithSameKeyAlreadyExistsException($key, $existingTraitDef);
        }

        $traitDef = new TraitDef(
            $key,
            $label,
            $description,
            $type,
        );

        $this->entityManager->persist($traitDef);
        $this->entityManager->flush();

        return $traitDef;
    }

}