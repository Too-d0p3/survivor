<?php

declare(strict_types=1);

namespace App\Domain\Game\Result;

use App\Domain\Game\MajorEvent;

final readonly class ExtractMajorEventsResult
{
    /** @var array<int, MajorEvent> */
    public array $majorEvents;

    /**
     * @param array<int, MajorEvent> $majorEvents
     */
    public function __construct(array $majorEvents)
    {
        $this->majorEvents = $majorEvents;
    }
}
