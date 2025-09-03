<?php

namespace App\Domain\Player;

use App\Domain\Ai\AiPlayerService;
use App\Domain\TraitDef\TraitDefRepository;
use Doctrine\ORM\EntityManagerInterface;

class PlayerService
{
    public function __construct(private EntityManagerInterface $em, private TraitDefRepository $traitDefRepository, private AiPlayerService $aiPlayerService) {}

    public function generatePlayerTraitsFromDescription($description): array
    {
        $traits = $this->traitDefRepository->findAll();

        return $this->aiPlayerService->generatePlayerTraitsFromDescription($description, $traits);
    }

    public function generatePlayerTraitsSummaryDescription(array $playerTraits): array
    {
        return $this->aiPlayerService->generatePlayerTraitsSummaryDescription($playerTraits);
    }
}