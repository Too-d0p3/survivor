<?php

declare(strict_types=1);

namespace App\Domain\Ai\Prompt;

use App\Domain\Ai\Exceptions\PromptTemplateNotFoundException;

final readonly class PromptLoader
{
    private string $templateDirectory;

    public function __construct(string $templateDirectory)
    {
        $this->templateDirectory = $templateDirectory;
    }

    /**
     * @param array<string, string> $variables
     */
    public function load(string $templateName, array $variables = []): string
    {
        $path = $this->templateDirectory . '/' . $templateName . '.md';

        if (!file_exists($path)) {
            throw new PromptTemplateNotFoundException($templateName);
        }

        $content = file_get_contents($path);
        if ($content === false) {
            throw new PromptTemplateNotFoundException($templateName);
        }

        foreach ($variables as $key => $value) {
            $content = str_replace('{{ ' . $key . ' }}', $value, $content);
        }

        return trim($content);
    }
}
