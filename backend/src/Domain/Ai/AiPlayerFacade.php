<?php

declare(strict_types=1);

namespace App\Domain\Ai;

use App\Domain\Ai\Log\AiLog;
use App\Domain\Ai\Result\GenerateBatchSummaryResult;
use App\Domain\Ai\Result\GenerateSummaryResult;
use App\Domain\Ai\Result\GenerateTraitsResult;
use App\Domain\Ai\Service\AiPlayerService;
use App\Domain\TraitDef\TraitDef;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

final class AiPlayerFacade
{
    private readonly EntityManagerInterface $entityManager;

    private readonly AiPlayerService $aiPlayerService;

    public function __construct(
        EntityManagerInterface $entityManager,
        AiPlayerService $aiPlayerService,
    ) {
        $this->entityManager = $entityManager;
        $this->aiPlayerService = $aiPlayerService;
    }

    /**
     * @param array<int, TraitDef> $traits
     */
    public function generatePlayerTraitsFromDescription(string $description, array $traits): GenerateTraitsResult
    {
        $now = new DateTimeImmutable();
        $serviceResult = $this->aiPlayerService->generatePlayerTraitsFromDescription($description, $traits, $now);
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
        $serviceResult = $this->aiPlayerService->generatePlayerTraitsSummaryDescription($traitStrengths, $now);
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
        $serviceResult = $this->aiPlayerService->generateBatchPlayerTraitsSummaryDescriptions($playerTraitStrengths, $now);
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
