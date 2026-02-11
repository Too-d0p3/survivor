<?php

declare(strict_types=1);

namespace App\Domain\Player;

use App\Domain\Ai\AiPlayerService;
use App\Domain\Player\Trait\PlayerTrait;
use App\Domain\TraitDef\TraitDefRepository;
use Doctrine\ORM\EntityManagerInterface;

class PlayerService
{
    private EntityManagerInterface $em;

    private TraitDefRepository $traitDefRepository;

    private AiPlayerService $aiPlayerService;

    public function __construct(
        EntityManagerInterface $em,
        TraitDefRepository $traitDefRepository,
        AiPlayerService $aiPlayerService,
    ) {
        $this->em = $em;
        $this->traitDefRepository = $traitDefRepository;
        $this->aiPlayerService = $aiPlayerService;
    }

    /**
     * @return array<string, mixed>
     */
    public function generatePlayerTraitsFromDescription(string $description): array
    {
        $traits = $this->traitDefRepository->findAll();

        return $this->aiPlayerService->generatePlayerTraitsFromDescription($description, $traits);
    }

    /**
     * @param array<int, PlayerTrait> $playerTraits
     * @return array<string, mixed>
     */
    public function generatePlayerTraitsSummaryDescription(array $playerTraits): array
    {
        return $this->aiPlayerService->generatePlayerTraitsSummaryDescription($playerTraits);
    }
}
