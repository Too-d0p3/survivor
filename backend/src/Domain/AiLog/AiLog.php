<?php

namespace App\Domain\AiLog;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AiLogRepository::class)]
class AiLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?DateTimeImmutable $createdAt = null;

    #[ORM\Column(length: 255)]
    private ?string $modelName = null;

    #[ORM\Column(length: 512, nullable: true)]
    private ?string $apiUrl = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $actionName = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $userPrompt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $systemPrompt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $requestJson = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $responseJson = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $returnContent = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $duration = null; // Duration in milliseconds

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getModelName(): ?string
    {
        return $this->modelName;
    }

    public function setModelName(string $modelName): static
    {
        $this->modelName = $modelName;

        return $this;
    }

    public function getApiUrl(): ?string
    {
        return $this->apiUrl;
    }

    public function setApiUrl(?string $apiUrl): static
    {
        $this->apiUrl = $apiUrl;

        return $this;
    }

    public function getActionName(): ?string
    {
        return $this->actionName;
    }

    public function setActionName(?string $actionName): static
    {
        $this->actionName = $actionName;

        return $this;
    }

    public function getUserPrompt(): ?string
    {
        return $this->userPrompt;
    }

    public function setUserPrompt(?string $userPrompt): static
    {
        $this->userPrompt = $userPrompt;

        return $this;
    }

    public function getSystemPrompt(): ?string
    {
        return $this->systemPrompt;
    }

    public function setSystemPrompt(?string $systemPrompt): static
    {
        $this->systemPrompt = $systemPrompt;

        return $this;
    }

    public function getRequestJson(): ?string
    {
        return $this->requestJson;
    }

    public function setRequestJson(?string $requestJson): static
    {
        $this->requestJson = $requestJson;

        return $this;
    }

    public function getResponseJson(): ?string
    {
        return $this->responseJson;
    }

    public function setResponseJson(?string $responseJson): static
    {
        $this->responseJson = $responseJson;

        return $this;
    }

    public function getReturnContent(): ?string
    {
        return $this->returnContent;
    }

    public function setReturnContent(?string $returnContent): static
    {
        $this->returnContent = $returnContent;

        return $this;
    }

    public function getDuration(): ?int
    {
        return $this->duration;
    }

    public function setDuration(?int $duration): static
    {
        $this->duration = $duration;

        return $this;
    }
} 