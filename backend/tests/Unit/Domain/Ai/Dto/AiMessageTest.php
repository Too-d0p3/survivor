<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Ai\Dto;

use App\Domain\Ai\Dto\AiMessage;
use PHPUnit\Framework\TestCase;

final class AiMessageTest extends TestCase
{
    public function testUserFactoryMethodCreatesUserMessage(): void
    {
        $message = AiMessage::user('Hello, AI!');

        self::assertSame('user', $message->getRole());
        self::assertSame('Hello, AI!', $message->getContent());
    }

    public function testModelFactoryMethodCreatesModelMessage(): void
    {
        $message = AiMessage::model('Hello, human!');

        self::assertSame('model', $message->getRole());
        self::assertSame('Hello, human!', $message->getContent());
    }

    public function testUserMessageWithEmptyContent(): void
    {
        $message = AiMessage::user('');

        self::assertSame('user', $message->getRole());
        self::assertSame('', $message->getContent());
    }

    public function testModelMessageWithMultilineContent(): void
    {
        $content = "Line 1\nLine 2\nLine 3";
        $message = AiMessage::model($content);

        self::assertSame('model', $message->getRole());
        self::assertSame($content, $message->getContent());
    }
}
