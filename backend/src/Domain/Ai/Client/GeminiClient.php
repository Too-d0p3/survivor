<?php

declare(strict_types=1);

namespace App\Domain\Ai\Client;

use App\Domain\Ai\Dto\AiRequest;
use App\Domain\Ai\Exceptions\AiRateLimitExceededException;
use App\Domain\Ai\Exceptions\AiRequestFailedException;
use App\Domain\Ai\Exceptions\AiResponseBlockedBySafetyException;
use App\Domain\Ai\Exceptions\AiResponseParsingFailedException;
use App\Domain\Ai\Result\AiResponse;

interface GeminiClient
{
    /**
     * @throws AiRequestFailedException
     * @throws AiRateLimitExceededException
     * @throws AiResponseBlockedBySafetyException
     * @throws AiResponseParsingFailedException
     */
    public function request(AiRequest $aiRequest): AiResponse;
}
