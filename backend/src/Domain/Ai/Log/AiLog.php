<?php

declare(strict_types=1);

namespace App\Domain\Ai\Log;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AiLogRepository::class)]
final class AiLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(length: 255)]
    private string $modelName;

    #[ORM\Column(length: 512, nullable: true)]
    private ?string $apiUrl;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $actionName;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $userPrompt;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $systemPrompt;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $requestJson;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $responseJson = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $returnContent = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $duration = null;

    public function __construct(
        string $modelName,
        DateTimeImmutable $createdAt,
        ?string $apiUrl = null,
        ?string $actionName = null,
        ?string $userPrompt = null,
        ?string $systemPrompt = null,
        ?string $requestJson = null,
    ) {
        $this->modelName = $modelName;
        $this->createdAt = $createdAt;
        $this->apiUrl = $apiUrl;
        $this->actionName = $actionName;
        $this->userPrompt = $userPrompt;
        $this->systemPrompt = $systemPrompt;
        $this->requestJson = $requestJson;
    }

    public function recordResponse(string $responseJson, int $duration, string $returnContent): void
    {
        $this->responseJson = $responseJson;
        $this->duration = $duration;
        $this->returnContent = $returnContent;
    }

    public function getId(): ?int
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

    public function getApiUrl(): ?string
    {
        return $this->apiUrl;
    }

    public function getActionName(): ?string
    {
        return $this->actionName;
    }

    public function getUserPrompt(): ?string
    {
        return $this->userPrompt;
    }

    public function getSystemPrompt(): ?string
    {
        return $this->systemPrompt;
    }

    public function getRequestJson(): ?string
    {
        return $this->requestJson;
    }

    public function getResponseJson(): ?string
    {
        return $this->responseJson;
    }

    public function getReturnContent(): ?string
    {
        return $this->returnContent;
    }

    public function getDuration(): ?int
    {
        return $this->duration;
    }
}
