<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Ai\Result;

use App\Domain\Ai\Result\GenerateTraitsResult;
use PHPUnit\Framework\TestCase;

final class GenerateTraitsResultTest extends TestCase
{
    public function testConstructorSetsAllProperties(): void
    {
        $traitScores = [
            'charisma' => 0.8,
            'intelligence' => 0.6,
            'strength' => 0.9,
        ];

        $result = new GenerateTraitsResult($traitScores, 'A strong and charismatic leader');

        self::assertSame($traitScores, $result->getTraitScores());
        self::assertSame('A strong and charismatic leader', $result->getSummary());
    }

    public function testConstructorWithEmptyTraitScores(): void
    {
        $result = new GenerateTraitsResult([], 'No traits detected');

        self::assertSame([], $result->getTraitScores());
        self::assertSame('No traits detected', $result->getSummary());
    }

    public function testConstructorWithSingleTrait(): void
    {
        $traitScores = ['loyalty' => 1.0];

        $result = new GenerateTraitsResult($traitScores, 'Extremely loyal');

        self::assertSame($traitScores, $result->getTraitScores());
        self::assertSame('Extremely loyal', $result->getSummary());
    }

    public function testConstructorWithBoundaryScores(): void
    {
        $traitScores = [
            'min_trait' => 0.0,
            'max_trait' => 1.0,
            'mid_trait' => 0.5,
        ];

        $result = new GenerateTraitsResult($traitScores, 'Mixed personality');

        self::assertSame($traitScores, $result->getTraitScores());
    }
}
