<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Ai\Result;

use App\Domain\Ai\Result\GenerateSummaryResult;
use PHPUnit\Framework\TestCase;

final class GenerateSummaryResultTest extends TestCase
{
    public function testConstructorSetsSummary(): void
    {
        $result = new GenerateSummaryResult('This is a test summary');

        self::assertSame('This is a test summary', $result->getSummary());
    }

    public function testConstructorWithEmptySummary(): void
    {
        $result = new GenerateSummaryResult('');

        self::assertSame('', $result->getSummary());
    }

    public function testConstructorWithMultilineSummary(): void
    {
        $summary = "First paragraph.\n\nSecond paragraph with details.\n\nThird paragraph.";

        $result = new GenerateSummaryResult($summary);

        self::assertSame($summary, $result->getSummary());
    }

    public function testConstructorWithLongSummary(): void
    {
        $summary = str_repeat('This is a very long summary. ', 100);

        $result = new GenerateSummaryResult($summary);

        self::assertSame($summary, $result->getSummary());
    }
}
