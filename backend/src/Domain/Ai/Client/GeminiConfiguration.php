<?php

declare(strict_types=1);

namespace App\Domain\Ai\Client;

final readonly class GeminiConfiguration
{
    private string $apiKey;
    private string $model;
    private string $baseUrl;
    private float $defaultTemperature;

    public function __construct(
        string $apiKey,
        string $model,
        string $baseUrl,
        float $defaultTemperature,
    ) {
        $this->apiKey = $apiKey;
        $this->model = $model;
        $this->baseUrl = $baseUrl;
        $this->defaultTemperature = $defaultTemperature;
    }

    public function getApiKey(): string
    {
        return $this->apiKey;
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    public function getDefaultTemperature(): float
    {
        return $this->defaultTemperature;
    }

    public function getEndpointUrl(): string
    {
        return sprintf('%s/models/%s:generateContent', $this->baseUrl, $this->model);
    }
}
