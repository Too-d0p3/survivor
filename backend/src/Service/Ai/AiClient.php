<?php

namespace App\Service\Ai;

use App\Entity\AiLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class AiClient
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private EntityManagerInterface $em,
        private string              $baseUrl = 'http://192.168.1.155:1234/v1/chat/completions', // nebo uprav podle LM studia
        private string $aiModelName = 'local', //TMP
    )
    {
    }

    public function createAiLog(string $actionName, string $userPrompt, string $systemPrompt, string $requestJson): AiLog
    {
        $log = new AiLog();
        $log->setModelName($this->aiModelName); //tmp
        $log->setApiUrl($this->baseUrl);
        $log->setActionName($actionName);
        $log->setUserPrompt($userPrompt);
        $log->setSystemPrompt($systemPrompt);
        $log->setRequestJson($requestJson);
        $this->em->persist($log);
        $this->em->flush();

        return $log;
    }

    public function ask(string $actionName, string $systemPrompt, array $messages, float $temperature = 0.6): string|array
    {
        $requestJson = [
            'model' => 'local-model', // LM Studio model name (pokud je potřeba)
            'temperature' => $temperature,
            'messages' => array_merge(
                [['role' => 'system', 'content' => $systemPrompt]],
                $messages
            )
        ];

        //TODO array of messages
        $aiLog = $this->createAiLog($actionName, $messages[0]['content'], $systemPrompt, json_encode($requestJson));
        $response = $this->httpClient->request('POST', $this->baseUrl, [ 'json' => $requestJson ]);

        $aiLog->setResponseJson($response->getContent()); //$response->getContent() stáhne content a provede request
        $aiLog->setDuration((int) round(($response->getInfo('total_time') ?? 0) * 1000));

        $data = $response->toArray();

        $content = $data['choices'][0]['message']['content'];
        $content = trim($content);

        if(str_starts_with($content, 'json')){
            $content = substr($content, strpos($content, '{'));
        }

        if (preg_match('/\{.*\}/s', $content, $matches)) {
            $json = $matches[0];
            $decoded = json_decode($json, true);

            $aiLog->setReturnContent($json);
            $this->em->persist($aiLog);
            $this->em->flush();

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \RuntimeException('JSON parsing failed: ' . json_last_error_msg());
            }

            return $decoded;
        }

        $aiLog->setReturnContent($content ?? '');
        $this->em->persist($aiLog);
        $this->em->flush();

        return $content ?? '';
    }
}
