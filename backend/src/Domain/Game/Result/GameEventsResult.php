<?php

declare(strict_types=1);

namespace App\Domain\Game\Result;

use App\Domain\Game\GameEvent;

final readonly class GameEventsResult
{
    /** @var array<int, GameEvent> */
    public array $events;

    public int $totalCount;

    public int $limit;

    public int $offset;

    /**
     * @param array<int, GameEvent> $events
     */
    public function __construct(array $events, int $totalCount, int $limit, int $offset)
    {
        $this->events = $events;
        $this->totalCount = $totalCount;
        $this->limit = $limit;
        $this->offset = $offset;
    }
}
