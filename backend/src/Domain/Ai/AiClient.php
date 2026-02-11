<?php

declare(strict_types=1);

namespace App\Domain\Ai;

use App\Domain\Ai\Log\AiLog;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class AiClient
{
    private readonly HttpClientInterface $httpClient;

    private readonly EntityManagerInterface $entityManager;

    private readonly string $baseUrl;

    private readonly string $aiModelName;

    public function __construct(
        HttpClientInterface $httpClient,
        EntityManagerInterface $entityManager,
        string $baseUrl = 'http://192.168.1.2:1234/v1/chat/completions',
        string $aiModelName = 'local',
    ) {
        $this->httpClient = $httpClient;
        $this->entityManager = $entityManager;
        $this->baseUrl = $baseUrl;
        $this->aiModelName = $aiModelName;
    }

    /**
     * @param array<int, array<string, string>> $messages
     * @return string|array<string, mixed>
     */
    public function ask(
        string $actionName,
        string $systemPrompt,
        array $messages,
        DateTimeImmutable $createdAt,
        float $temperature = 0.6,
    ): string|array {
        $requestJson = [
            'model' => 'local-model',
            'temperature' => $temperature,
            'messages' => array_merge(
                [['role' => 'system', 'content' => $systemPrompt]],
                $messages,
            ),
        ];

        $aiLog = $this->createAiLog(
            $actionName,
            $messages[0]['content'],
            $systemPrompt,
            json_encode($requestJson, JSON_THROW_ON_ERROR),
            $createdAt,
        );
        $response = $this->httpClient->request('POST', $this->baseUrl, ['json' => $requestJson]);

        $responseJson = $response->getContent();
        $totalTime = $response->getInfo('total_time');
        $duration = (int) round((is_float($totalTime) ? $totalTime : 0.0) * 1000);

        /** @var array<string, mixed> $data */
        $data = $response->toArray();

        /** @var array<int, array<string, array<string, string>>> $choices */
        $choices = $data['choices'];
        $content = $choices[0]['message']['content'];
        $content = trim($content);

        if (str_starts_with($content, 'json')) {
            $bracePosition = strpos($content, '{');
            if ($bracePosition !== false) {
                $content = substr($content, $bracePosition);
            }
        }

        if (preg_match('/\{.*\}/s', $content, $matches) === 1) {
            $json = $matches[0];
            $decoded = json_decode($json, true);

            $aiLog->recordResponse($responseJson, $duration, $json);
            $this->entityManager->persist($aiLog);
            $this->entityManager->flush();

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new RuntimeException(sprintf('JSON parsing failed: %s', json_last_error_msg()));
            }

            /** @var array<string, mixed> $decoded */
            return $decoded;
        }

        $aiLog->recordResponse($responseJson, $duration, $content);
        $this->entityManager->persist($aiLog);
        $this->entityManager->flush();

        return $content;
    }

    private function createAiLog(
        string $actionName,
        string $userPrompt,
        string $systemPrompt,
        string $requestJson,
        DateTimeImmutable $createdAt,
    ): AiLog {
        $log = new AiLog(
            $this->aiModelName,
            $createdAt,
            $this->baseUrl,
            $actionName,
            $userPrompt,
            $systemPrompt,
            $requestJson,
        );
        $this->entityManager->persist($log);
        $this->entityManager->flush();

        return $log;
    }
}
