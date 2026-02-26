<?php

declare(strict_types=1);

namespace App\Domain\Player;

use App\Domain\Ai\Log\AiLog;
use App\Domain\Ai\Result\GenerateBatchSummaryResult;
use App\Domain\Ai\Result\GenerateSummaryResult;
use App\Domain\Ai\Result\GenerateTraitsResult;
use App\Domain\TraitDef\TraitDef;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

final class PlayerFacade
{
    private readonly EntityManagerInterface $entityManager;

    private readonly PlayerService $playerService;

    public function __construct(
        EntityManagerInterface $entityManager,
        PlayerService $playerService,
    ) {
        $this->entityManager = $entityManager;
        $this->playerService = $playerService;
    }

    /**
     * @param array<int, TraitDef> $traits
     */
    public function generatePlayerTraitsFromDescription(string $description, array $traits): GenerateTraitsResult
    {
        $now = new DateTimeImmutable();
        $serviceResult = $this->playerService->generatePlayerTraitsFromDescription($description, $traits, $now);
        $this->persistLogs($serviceResult->getLogs());

        if (!$serviceResult->isSuccess()) {
            throw $serviceResult->getError();
        }

        return $serviceResult->getResult();
    }

    /**
     * @param array<string, string> $traitStrengths
     */
    public function generatePlayerTraitsSummaryDescription(array $traitStrengths): GenerateSummaryResult
    {
        $now = new DateTimeImmutable();
        $serviceResult = $this->playerService->generatePlayerTraitsSummaryDescription($traitStrengths, $now);
        $this->persistLogs($serviceResult->getLogs());

        if (!$serviceResult->isSuccess()) {
            throw $serviceResult->getError();
        }

        return $serviceResult->getResult();
    }

    /**
     * @param array<int, array<string, string>> $playerTraitStrengths
     */
    public function generateBatchPlayerTraitsSummaryDescriptions(array $playerTraitStrengths): GenerateBatchSummaryResult
    {
        $now = new DateTimeImmutable();
        $serviceResult = $this->playerService->generateBatchPlayerTraitsSummaryDescriptions($playerTraitStrengths, $now);
        $this->persistLogs($serviceResult->getLogs());

        if (!$serviceResult->isSuccess()) {
            throw $serviceResult->getError();
        }

        return $serviceResult->getResult();
    }

    /**
     * @param array<int, AiLog> $logs
     */
    private function persistLogs(array $logs): void
    {
        foreach ($logs as $log) {
            $this->entityManager->persist($log);
        }

        $this->entityManager->flush();
    }
}
