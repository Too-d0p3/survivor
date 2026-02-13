<?php

declare(strict_types=1);

namespace App\Domain\Ai\Dto;

final readonly class AiRequest
{
    private string $actionName;
    private string $systemInstruction;
    /** @var array<int, AiMessage> */
    private array $messages;
    private ?float $temperature;
    private ?AiResponseSchema $responseSchema;

    /**
     * @param array<int, AiMessage> $messages
     */
    public function __construct(
        string $actionName,
        string $systemInstruction,
        array $messages,
        ?float $temperature = null,
        ?AiResponseSchema $responseSchema = null,
    ) {
        $this->actionName = $actionName;
        $this->systemInstruction = $systemInstruction;
        $this->messages = $messages;
        $this->temperature = $temperature;
        $this->responseSchema = $responseSchema;
    }

    public function getActionName(): string
    {
        return $this->actionName;
    }

    public function getSystemInstruction(): string
    {
        return $this->systemInstruction;
    }

    /**
     * @return array<int, AiMessage>
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    public function getTemperature(): ?float
    {
        return $this->temperature;
    }

    public function getResponseSchema(): ?AiResponseSchema
    {
        return $this->responseSchema;
    }

    /**
     * @return array<string, mixed>
     */
    public function toGeminiRequestBody(float $defaultTemperature): array
    {
        $temperature = $this->temperature ?? $defaultTemperature;

        $contents = [];
        foreach ($this->messages as $message) {
            $contents[] = [
                'role' => $message->getRole(),
                'parts' => [
                    ['text' => $message->getContent()],
                ],
            ];
        }

        $generationConfig = [
            'temperature' => $temperature,
        ];

        if ($this->responseSchema !== null) {
            $generationConfig['responseMimeType'] = 'application/json';
            $generationConfig['responseSchema'] = $this->responseSchema->toArray();
        }

        return [
            'systemInstruction' => [
                'parts' => [
                    ['text' => $this->systemInstruction],
                ],
            ],
            'contents' => $contents,
            'generationConfig' => $generationConfig,
        ];
    }
}
