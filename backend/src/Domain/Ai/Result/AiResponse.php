<?php

declare(strict_types=1);

namespace App\Domain\Ai\Result;

final readonly class AiResponse
{
    private string $content;
    private TokenUsage $tokenUsage;
    private int $durationMs;
    private string $modelVersion;
    private string $rawResponseJson;
    private string $finishReason;

    public function __construct(
        string $content,
        TokenUsage $tokenUsage,
        int $durationMs,
        string $modelVersion,
        string $rawResponseJson,
        string $finishReason,
    ) {
        $this->content = $content;
        $this->tokenUsage = $tokenUsage;
        $this->durationMs = $durationMs;
        $this->modelVersion = $modelVersion;
        $this->rawResponseJson = $rawResponseJson;
        $this->finishReason = $finishReason;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getTokenUsage(): TokenUsage
    {
        return $this->tokenUsage;
    }

    public function getDurationMs(): int
    {
        return $this->durationMs;
    }

    public function getModelVersion(): string
    {
        return $this->modelVersion;
    }

    public function getRawResponseJson(): string
    {
        return $this->rawResponseJson;
    }

    public function getFinishReason(): string
    {
        return $this->finishReason;
    }
}
