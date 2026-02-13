<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Ai\Result;

use App\Domain\Ai\Result\TokenUsage;
use PHPUnit\Framework\TestCase;

final class TokenUsageTest extends TestCase
{
    public function testConstructorSetsAllProperties(): void
    {
        $tokenUsage = new TokenUsage(100, 50, 150);

        self::assertSame(100, $tokenUsage->getPromptTokenCount());
        self::assertSame(50, $tokenUsage->getCandidatesTokenCount());
        self::assertSame(150, $tokenUsage->getTotalTokenCount());
    }

    public function testConstructorWithZeroValues(): void
    {
        $tokenUsage = new TokenUsage(0, 0, 0);

        self::assertSame(0, $tokenUsage->getPromptTokenCount());
        self::assertSame(0, $tokenUsage->getCandidatesTokenCount());
        self::assertSame(0, $tokenUsage->getTotalTokenCount());
    }

    public function testConstructorWithLargeValues(): void
    {
        $tokenUsage = new TokenUsage(10000, 5000, 15000);

        self::assertSame(10000, $tokenUsage->getPromptTokenCount());
        self::assertSame(5000, $tokenUsage->getCandidatesTokenCount());
        self::assertSame(15000, $tokenUsage->getTotalTokenCount());
    }
}
