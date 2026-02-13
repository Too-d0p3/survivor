<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Ai\Prompt;

use App\Domain\Ai\Exceptions\PromptTemplateNotFoundException;
use App\Domain\Ai\Prompt\PromptLoader;
use PHPUnit\Framework\TestCase;

final class PromptLoaderTest extends TestCase
{
    private string $tempDir;

    private PromptLoader $promptLoader;

    public function testLoadReturnsTemplateContent(): void
    {
        $content = "This is a test template.\nWith multiple lines.";
        $this->createTemplateFile('test_template', $content);

        $result = $this->promptLoader->load('test_template');

        self::assertSame($content, $result);
    }

    public function testLoadReplacesPlaceholders(): void
    {
        $content = "Hello {{ name }}! Your traits are: {{ traits }}.";
        $this->createTemplateFile('placeholder_test', $content);

        $result = $this->promptLoader->load('placeholder_test', [
            'name' => 'John',
            'traits' => 'brave, loyal',
        ]);

        self::assertSame('Hello John! Your traits are: brave, loyal.', $result);
    }

    public function testLoadWithEmptyVariablesReturnsOriginalContent(): void
    {
        $content = "Template without placeholders.";
        $this->createTemplateFile('no_placeholders', $content);

        $result = $this->promptLoader->load('no_placeholders', []);

        self::assertSame($content, $result);
    }

    public function testLoadNonExistentTemplateThrowsException(): void
    {
        $this->expectException(PromptTemplateNotFoundException::class);
        $this->expectExceptionMessage("Prompt template 'non_existent' not found");

        $this->promptLoader->load('non_existent');
    }

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/prompt_loader_test_' . uniqid();
        mkdir($this->tempDir);

        $this->promptLoader = new PromptLoader($this->tempDir);
    }

    protected function tearDown(): void
    {
        $files = glob($this->tempDir . '/*.md');
        if ($files !== false) {
            foreach ($files as $file) {
                unlink($file);
            }
        }
        rmdir($this->tempDir);
    }

    private function createTemplateFile(string $name, string $content): void
    {
        file_put_contents($this->tempDir . '/' . $name . '.md', $content);
    }
}
