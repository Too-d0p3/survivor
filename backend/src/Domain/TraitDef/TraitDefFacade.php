<?php

declare(strict_types=1);

namespace App\Domain\TraitDef;

use App\Domain\TraitDef\Exceptions\CannotCreateTraitDefBecauseTraitDefWithSameKeyAlreadyExistsException;
use Doctrine\ORM\EntityManagerInterface;

final class TraitDefFacade
{
    private readonly TraitDefRepository $traitDefRepository;

    private readonly EntityManagerInterface $entityManager;

    public function __construct(
        TraitDefRepository $traitDefRepository,
        EntityManagerInterface $entityManager,
    ) {
        $this->traitDefRepository = $traitDefRepository;
        $this->entityManager = $entityManager;
    }

    public function createTraitDef(
        string $key,
        string $label,
        string $description,
        TraitType $type,
    ): TraitDef {
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
