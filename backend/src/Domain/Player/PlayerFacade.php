<?php

declare(strict_types=1);

namespace App\Domain\Player;

use App\Domain\Ai\AiPlayerFacade;
use App\Domain\TraitDef\TraitDefRepository;

final class PlayerFacade
{
    private readonly TraitDefRepository $traitDefRepository;

    private readonly AiPlayerFacade $aiPlayerFacade;

    public function __construct(
        TraitDefRepository $traitDefRepository,
        AiPlayerFacade $aiPlayerFacade,
    ) {
        $this->traitDefRepository = $traitDefRepository;
        $this->aiPlayerFacade = $aiPlayerFacade;
    }

    /**
     * @return array<string, mixed>
     */
    public function generatePlayerTraitsFromDescription(string $description): array
    {
        $traits = array_values($this->traitDefRepository->findAll());

        return $this->aiPlayerFacade->generatePlayerTraitsFromDescription($description, $traits);
    }

    /**
     * @param array<string, string> $traitStrengths
     * @return array<string, mixed>
     */
    public function generatePlayerTraitsSummaryDescription(array $traitStrengths): array
    {
        return $this->aiPlayerFacade->generatePlayerTraitsSummaryDescription($traitStrengths);
    }
}
