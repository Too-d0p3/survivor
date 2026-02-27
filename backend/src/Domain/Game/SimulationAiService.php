<?php

declare(strict_types=1);

namespace App\Domain\Game;

use App\Domain\Ai\AiExecutor;
use App\Domain\Ai\Operation\SimulateTickOperation;
use App\Domain\Ai\Operation\SimulationEventInput;
use App\Domain\Ai\Operation\SimulationPlayerInput;
use App\Domain\Ai\Operation\SimulationRelationshipInput;
use App\Domain\Ai\Result\SimulateTickServiceResult;
use DateTimeImmutable;

final readonly class SimulationAiService
{
    private AiExecutor $executor;

    public function __construct(AiExecutor $executor)
    {
        $this->executor = $executor;
    }

    /**
     * @param array<int, SimulationPlayerInput> $players
     * @param array<int, SimulationRelationshipInput> $relationships
     * @param array<int, SimulationEventInput> $recentEvents
     */
    public function simulateTick(
        int $day,
        int $hour,
        string $actionText,
        array $players,
        array $relationships,
        array $recentEvents,
        int $humanPlayerIndex,
        DateTimeImmutable $now,
    ): SimulateTickServiceResult {
        $operation = new SimulateTickOperation(
            $day,
            $hour,
            $actionText,
            $players,
            $relationships,
            $recentEvents,
            $humanPlayerIndex,
        );

        $callResult = $this->executor->execute($operation, $now);

        if (!$callResult->isSuccess()) {
            return SimulateTickServiceResult::failure([$callResult->getLog()], $callResult->getError());
        }

        return SimulateTickServiceResult::success($callResult->getResult(), [$callResult->getLog()]);
    }
}
