<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Ai\Operation;

use App\Domain\Ai\Exceptions\AiResponseParsingFailedException;
use App\Domain\Ai\Operation\GenerateBatchPlayerSummariesOperation;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class GenerateBatchPlayerSummariesOperationTest extends TestCase
{
    public function testGetActionName(): void
    {
        $operation = new GenerateBatchPlayerSummariesOperation([['leadership' => '0.85']]);

        self::assertSame('generateBatchPlayerTraitsSummaryDescriptions', $operation->getActionName());
    }

    public function testGetTemplateName(): void
    {
        $operation = new GenerateBatchPlayerSummariesOperation([['leadership' => '0.85']]);

        self::assertSame('generate_batch_player_summaries', $operation->getTemplateName());
    }

    public function testGetTemplateVariablesReturnsEmptyArray(): void
    {
        $operation = new GenerateBatchPlayerSummariesOperation([['leadership' => '0.85']]);

        self::assertSame([], $operation->getTemplateVariables());
    }

    public function testGetMessagesFormatsCorrectly(): void
    {
        $operation = new GenerateBatchPlayerSummariesOperation([
            ['leadership' => '0.85', 'empathy' => '0.60'],
            ['leadership' => '0.30', 'empathy' => '0.90'],
        ]);

        $messages = $operation->getMessages();

        self::assertCount(1, $messages);
        self::assertSame('user', $messages[0]->getRole());

        $expected = "Hráč 1:\nleadership: 0.85\nempathy: 0.60\n\nHráč 2:\nleadership: 0.30\nempathy: 0.90";
        self::assertSame($expected, $messages[0]->getContent());
    }

    public function testGetResponseSchema(): void
    {
        $operation = new GenerateBatchPlayerSummariesOperation([['leadership' => '0.85']]);
        $schema = $operation->getResponseSchema();

        self::assertSame('object', $schema->getType());
        self::assertSame(['summaries'], $schema->getRequired());
    }

    public function testGetTemperatureReturnsNull(): void
    {
        $operation = new GenerateBatchPlayerSummariesOperation([['leadership' => '0.85']]);

        self::assertNull($operation->getTemperature());
    }

    public function testConstructorValidatesNonNumericValue(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Trait strength value for "leadership" is not numeric');

        new GenerateBatchPlayerSummariesOperation([['leadership' => 'abc']]);
    }

    public function testConstructorValidatesValueOutOfRange(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Trait strength value for "leadership" is out of range [0.0, 1.0]: 1.5');

        new GenerateBatchPlayerSummariesOperation([['leadership' => '1.5']]);
    }

    public function testConstructorFormatsValues(): void
    {
        $operation = new GenerateBatchPlayerSummariesOperation([['leadership' => '0.8']]);
        $messages = $operation->getMessages();

        self::assertStringContainsString('leadership: 0.80', $messages[0]->getContent());
    }

    public function testParseValidJson(): void
    {
        $operation = new GenerateBatchPlayerSummariesOperation([
            ['leadership' => '0.85'],
            ['leadership' => '0.30'],
        ]);

        $content = json_encode([
            'summaries' => [
                ['player_index' => 1, 'summary' => 'Strong leader.'],
                ['player_index' => 2, 'summary' => 'Empathetic player.'],
            ],
        ], JSON_THROW_ON_ERROR);

        $result = $operation->parse($content);

        self::assertSame(['Strong leader.', 'Empathetic player.'], $result->getSummaries());
    }

    public function testParseSinglePlayer(): void
    {
        $operation = new GenerateBatchPlayerSummariesOperation([['leadership' => '0.85']]);

        $content = json_encode([
            'summaries' => [
                ['player_index' => 1, 'summary' => 'Solo player.'],
            ],
        ], JSON_THROW_ON_ERROR);

        $result = $operation->parse($content);

        self::assertSame(['Solo player.'], $result->getSummaries());
    }

    public function testParseReordersByPlayerIndex(): void
    {
        $operation = new GenerateBatchPlayerSummariesOperation([
            ['leadership' => '0.85'],
            ['leadership' => '0.30'],
        ]);

        $content = json_encode([
            'summaries' => [
                ['player_index' => 2, 'summary' => 'Second player.'],
                ['player_index' => 1, 'summary' => 'First player.'],
            ],
        ], JSON_THROW_ON_ERROR);

        $result = $operation->parse($content);

        self::assertSame(['First player.', 'Second player.'], $result->getSummaries());
    }

    public function testParseInvalidJsonThrowsException(): void
    {
        $operation = new GenerateBatchPlayerSummariesOperation([['leadership' => '0.85']]);

        $this->expectException(AiResponseParsingFailedException::class);
        $this->expectExceptionMessage('Invalid JSON');

        $operation->parse('not valid json{');
    }

    public function testParseMissingSummariesKeyThrowsException(): void
    {
        $operation = new GenerateBatchPlayerSummariesOperation([['leadership' => '0.85']]);

        $content = json_encode(['other' => 'value'], JSON_THROW_ON_ERROR);

        $this->expectException(AiResponseParsingFailedException::class);
        $this->expectExceptionMessage('Missing "summaries" key in response');

        $operation->parse($content);
    }

    public function testParseSummariesNotArrayThrowsException(): void
    {
        $operation = new GenerateBatchPlayerSummariesOperation([['leadership' => '0.85']]);

        $content = json_encode(['summaries' => 'not-array'], JSON_THROW_ON_ERROR);

        $this->expectException(AiResponseParsingFailedException::class);
        $this->expectExceptionMessage('"summaries" value is not an array');

        $operation->parse($content);
    }

    public function testParseWrongCountThrowsException(): void
    {
        $operation = new GenerateBatchPlayerSummariesOperation([
            ['leadership' => '0.85'],
            ['leadership' => '0.30'],
        ]);

        $content = json_encode([
            'summaries' => [
                ['player_index' => 1, 'summary' => 'Only one.'],
            ],
        ], JSON_THROW_ON_ERROR);

        $this->expectException(AiResponseParsingFailedException::class);
        $this->expectExceptionMessage('Expected 2 summaries, got 1');

        $operation->parse($content);
    }

    public function testParseMissingPlayerIndexThrowsException(): void
    {
        $operation = new GenerateBatchPlayerSummariesOperation([['leadership' => '0.85']]);

        $content = json_encode([
            'summaries' => [
                ['summary' => 'No index.'],
            ],
        ], JSON_THROW_ON_ERROR);

        $this->expectException(AiResponseParsingFailedException::class);
        $this->expectExceptionMessage('Missing or invalid player_index at index 0');

        $operation->parse($content);
    }

    public function testParseDuplicatePlayerIndexThrowsException(): void
    {
        $operation = new GenerateBatchPlayerSummariesOperation([
            ['leadership' => '0.85'],
            ['leadership' => '0.30'],
        ]);

        $content = json_encode([
            'summaries' => [
                ['player_index' => 1, 'summary' => 'First.'],
                ['player_index' => 1, 'summary' => 'Duplicate.'],
            ],
        ], JSON_THROW_ON_ERROR);

        $this->expectException(AiResponseParsingFailedException::class);
        $this->expectExceptionMessage('Duplicate player_index 1');

        $operation->parse($content);
    }

    public function testParseSummaryNotStringThrowsException(): void
    {
        $operation = new GenerateBatchPlayerSummariesOperation([['leadership' => '0.85']]);

        $content = json_encode([
            'summaries' => [
                ['player_index' => 1, 'summary' => 123],
            ],
        ], JSON_THROW_ON_ERROR);

        $this->expectException(AiResponseParsingFailedException::class);
        $this->expectExceptionMessage('"summary" value at index 0 is not a string');

        $operation->parse($content);
    }

    public function testParseSummaryExceedsLengthLimitThrowsException(): void
    {
        $operation = new GenerateBatchPlayerSummariesOperation([['leadership' => '0.85']]);

        $longSummary = str_repeat('a', 201);
        $content = json_encode([
            'summaries' => [
                ['player_index' => 1, 'summary' => $longSummary],
            ],
        ], JSON_THROW_ON_ERROR);

        $this->expectException(AiResponseParsingFailedException::class);
        $this->expectExceptionMessage('Summary at index 0 exceeds 200 character limit');

        $operation->parse($content);
    }

    public function testParsePlayerIndexBelowMinimumThrowsException(): void
    {
        $operation = new GenerateBatchPlayerSummariesOperation([['leadership' => '0.85']]);

        $content = json_encode([
            'summaries' => [
                ['player_index' => 0, 'summary' => 'Invalid index.'],
            ],
        ], JSON_THROW_ON_ERROR);

        $this->expectException(AiResponseParsingFailedException::class);
        $this->expectExceptionMessage('player_index 0 out of range');

        $operation->parse($content);
    }

    public function testParsePlayerIndexAboveMaximumThrowsException(): void
    {
        $operation = new GenerateBatchPlayerSummariesOperation([
            ['leadership' => '0.85'],
            ['leadership' => '0.30'],
        ]);

        $content = json_encode([
            'summaries' => [
                ['player_index' => 1, 'summary' => 'Valid.'],
                ['player_index' => 3, 'summary' => 'Index too high.'],
            ],
        ], JSON_THROW_ON_ERROR);

        $this->expectException(AiResponseParsingFailedException::class);
        $this->expectExceptionMessage('player_index 3 out of range');

        $operation->parse($content);
    }
}
