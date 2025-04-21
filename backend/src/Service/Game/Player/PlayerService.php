<?php

namespace App\Service\Game\Player;

use App\Repository\TraitDefRepository;
use App\Service\Ai\Player\AiPlayerService;
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