<?php

declare(strict_types=1);

namespace App\Domain\Player;

use App\Domain\Ai\AiExecutor;
use App\Domain\Ai\Operation\GenerateBatchPlayerSummariesOperation;
use App\Domain\Ai\Operation\GeneratePlayerTraitsOperation;
use App\Domain\Ai\Operation\InitializeRelationshipsOperation;
use App\Domain\Ai\Operation\PlayerRelationshipInput;
use App\Domain\Ai\Result\GenerateBatchPlayerSummariesServiceResult;
use App\Domain\Ai\Result\GeneratePlayerSummaryServiceResult;
use App\Domain\Ai\Result\GeneratePlayerTraitsServiceResult;
use App\Domain\Ai\Result\GenerateSummaryResult;
use App\Domain\Ai\Result\InitializeRelationshipsServiceResult;
use App\Domain\TraitDef\TraitDef;
use DateTimeImmutable;

final readonly class PlayerService
{
    private AiExecutor $executor;

    public function __construct(
        AiExecutor $executor,
    ) {
        $this->executor = $executor;
    }

    /**
     * @param array<int, TraitDef> $traits
     */
    public function generatePlayerTraitsFromDescription(string $description, array $traits, DateTimeImmutable $now): GeneratePlayerTraitsServiceResult
    {
        $operation = new GeneratePlayerTraitsOperation($description, $traits);
        $callResult = $this->executor->execute($operation, $now);

        if (!$callResult->isSuccess()) {
            return GeneratePlayerTraitsServiceResult::failure([$callResult->getLog()], $callResult->getError());
        }

        return GeneratePlayerTraitsServiceResult::success($callResult->getResult(), [$callResult->getLog()]);
    }

    /**
     * @param array<string, string> $traitStrengths
     */
    public function generatePlayerTraitsSummaryDescription(array $traitStrengths, DateTimeImmutable $now): GeneratePlayerSummaryServiceResult
    {
        $batchResult = $this->generateBatchPlayerTraitsSummaryDescriptions([$traitStrengths], $now);

        if (!$batchResult->isSuccess()) {
            return GeneratePlayerSummaryServiceResult::failure($batchResult->getLogs(), $batchResult->getError());
        }

        return GeneratePlayerSummaryServiceResult::success(
            new GenerateSummaryResult($batchResult->getResult()->getSummaries()[0]),
            $batchResult->getLogs(),
        );
    }

    /**
     * @param array<int, array<string, string>> $playerTraitStrengths
     */
    public function generateBatchPlayerTraitsSummaryDescriptions(array $playerTraitStrengths, DateTimeImmutable $now): GenerateBatchPlayerSummariesServiceResult
    {
        $operation = new GenerateBatchPlayerSummariesOperation($playerTraitStrengths);
        $callResult = $this->executor->execute($operation, $now);

        if (!$callResult->isSuccess()) {
            return GenerateBatchPlayerSummariesServiceResult::failure([$callResult->getLog()], $callResult->getError());
        }

        return GenerateBatchPlayerSummariesServiceResult::success($callResult->getResult(), [$callResult->getLog()]);
    }

    /**
     * @param array<int, PlayerRelationshipInput> $players
     */
    public function initializeRelationships(array $players, DateTimeImmutable $now): InitializeRelationshipsServiceResult
    {
        $operation = new InitializeRelationshipsOperation($players);
        $callResult = $this->executor->execute($operation, $now);

        if (!$callResult->isSuccess()) {
            return InitializeRelationshipsServiceResult::failure([$callResult->getLog()], $callResult->getError());
        }

        return InitializeRelationshipsServiceResult::success($callResult->getResult(), [$callResult->getLog()]);
    }

    /**
     * @param array<int, TraitDef> $traitDefs
     * @return array<string, string>
     */
    public function generateRandomTraitStrengths(array $traitDefs): array
    {
        $strengths = [];

        foreach ($traitDefs as $traitDef) {
            $strengths[$traitDef->getKey()] = number_format(random_int(0, 100) / 100, 2, '.', '');
        }

        return $strengths;
    }
}
