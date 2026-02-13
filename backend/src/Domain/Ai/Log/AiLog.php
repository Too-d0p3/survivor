<?php

declare(strict_types=1);

namespace App\Domain\Ai\Log;

use App\Domain\Ai\Result\AiResponse;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: AiLogRepository::class)]
#[ORM\Index(columns: ['action_name'], name: 'idx_ai_log_action_name')]
#[ORM\Index(columns: ['status'], name: 'idx_ai_log_status')]
#[ORM\Index(columns: ['created_at'], name: 'idx_ai_log_created_at')]
final class AiLog
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(length: 100)]
    private string $modelName;

    #[ORM\Column(length: 255)]
    private string $actionName;

    #[ORM\Column(type: Types::TEXT)]
    private string $systemPrompt;

    #[ORM\Column(type: Types::TEXT)]
    private string $userPrompt;

    #[ORM\Column(type: Types::TEXT)]
    private string $requestJson;

    #[ORM\Column(type: Types::FLOAT)]
    private float $temperature;

    #[ORM\Column(length: 20, enumType: AiLogStatus::class)]
    private AiLogStatus $status;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $responseJson = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $returnContent = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $promptTokenCount = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $candidatesTokenCount = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $totalTokenCount = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $durationMs = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $modelVersion = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $finishReason = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $errorMessage = null;

    public function __construct(
        string $modelName,
        DateTimeImmutable $createdAt,
        string $actionName,
        string $systemPrompt,
        string $userPrompt,
        string $requestJson,
        float $temperature,
    ) {
        $this->id = Uuid::v7();
        $this->modelName = $modelName;
        $this->createdAt = $createdAt;
        $this->actionName = $actionName;
        $this->systemPrompt = $systemPrompt;
        $this->userPrompt = $userPrompt;
        $this->requestJson = $requestJson;
        $this->temperature = $temperature;
        $this->status = AiLogStatus::Pending;
    }

    public function recordSuccess(AiResponse $response): void
    {
        $this->status = AiLogStatus::Success;
        $this->responseJson = $response->getRawResponseJson();
        $this->returnContent = $response->getContent();
        $this->promptTokenCount = $response->getTokenUsage()->getPromptTokenCount();
        $this->candidatesTokenCount = $response->getTokenUsage()->getCandidatesTokenCount();
        $this->totalTokenCount = $response->getTokenUsage()->getTotalTokenCount();
        $this->durationMs = $response->getDurationMs();
        $this->modelVersion = $response->getModelVersion();
        $this->finishReason = $response->getFinishReason();
    }

    public function recordError(string $errorMessage, ?int $durationMs = null): void
    {
        $this->status = AiLogStatus::Error;
        $this->errorMessage = $errorMessage;
        $this->durationMs = $durationMs;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getModelName(): string
    {
        return $this->modelName;
    }

    public function getActionName(): string
    {
        return $this->actionName;
    }

    public function getSystemPrompt(): string
    {
        return $this->systemPrompt;
    }

    public function getUserPrompt(): string
    {
        return $this->userPrompt;
    }

    public function getRequestJson(): string
    {
        return $this->requestJson;
    }

    public function getTemperature(): float
    {
        return $this->temperature;
    }

    public function getStatus(): AiLogStatus
    {
        return $this->status;
    }

    public function getResponseJson(): ?string
    {
        return $this->responseJson;
    }

    public function getReturnContent(): ?string
    {
        return $this->returnContent;
    }

    public function getPromptTokenCount(): ?int
    {
        return $this->promptTokenCount;
    }

    public function getCandidatesTokenCount(): ?int
    {
        return $this->candidatesTokenCount;
    }

    public function getTotalTokenCount(): ?int
    {
        return $this->totalTokenCount;
    }

    public function getDurationMs(): ?int
    {
        return $this->durationMs;
    }

    public function getModelVersion(): ?string
    {
        return $this->modelVersion;
    }

    public function getFinishReason(): ?string
    {
        return $this->finishReason;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }
}
