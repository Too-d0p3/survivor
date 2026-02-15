<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Ai\Service;

use App\Domain\Ai\Exceptions\AiResponseParsingFailedException;
use App\Domain\Ai\Service\AiResponseParser;
use App\Domain\TraitDef\TraitDef;
use App\Domain\TraitDef\TraitType;
use PHPUnit\Framework\TestCase;

final class AiResponseParserTest extends TestCase
{
    private AiResponseParser $parser;

    /** @var array<int, TraitDef> */
    private array $availableTraits;

    public function testParseGenerateTraitsResponseValidJson(): void
    {
        $content = json_encode([
            'traits' => [
                'leadership' => 0.8,
                'charisma' => 0.6,
                'endurance' => 0.7,
            ],
            'summary' => 'Strong leader with good social skills',
        ], JSON_THROW_ON_ERROR);

        $result = $this->parser->parseGenerateTraitsResponse($content, $this->availableTraits, 'test-action');

        self::assertSame(['leadership' => 0.8, 'charisma' => 0.6, 'endurance' => 0.7], $result->getTraitScores());
        self::assertSame('Strong leader with good social skills', $result->getSummary());
    }

    public function testParseGenerateTraitsResponseMissingTraitsKeyThrowsException(): void
    {
        $content = json_encode([
            'summary' => 'Summary without traits',
        ], JSON_THROW_ON_ERROR);

        $this->expectException(AiResponseParsingFailedException::class);
        $this->expectExceptionMessage('Missing "traits" key in response');

        $this->parser->parseGenerateTraitsResponse($content, $this->availableTraits, 'test-action');
    }

    public function testParseGenerateTraitsResponseMissingSummaryKeyThrowsException(): void
    {
        $content = json_encode([
            'traits' => [
                'leadership' => 0.8,
            ],
        ], JSON_THROW_ON_ERROR);

        $this->expectException(AiResponseParsingFailedException::class);
        $this->expectExceptionMessage('Missing "summary" key in response');

        $this->parser->parseGenerateTraitsResponse($content, $this->availableTraits, 'test-action');
    }

    public function testParseGenerateTraitsResponseInvalidJsonThrowsException(): void
    {
        $content = 'not valid json{';

        $this->expectException(AiResponseParsingFailedException::class);
        $this->expectExceptionMessage('Invalid JSON');

        $this->parser->parseGenerateTraitsResponse($content, $this->availableTraits, 'test-action');
    }

    public function testParseGenerateTraitsResponseUnknownTraitKeyThrowsException(): void
    {
        $content = json_encode([
            'traits' => [
                'unknown-trait' => 0.5,
            ],
            'summary' => 'Summary',
        ], JSON_THROW_ON_ERROR);

        $this->expectException(AiResponseParsingFailedException::class);
        $this->expectExceptionMessage('Unknown trait key "unknown-trait" in response');

        $this->parser->parseGenerateTraitsResponse($content, $this->availableTraits, 'test-action');
    }

    public function testParseGenerateTraitsResponseTraitScoreOutOfRangeThrowsException(): void
    {
        $content = json_encode([
            'traits' => [
                'leadership' => 1.5,
            ],
            'summary' => 'Summary',
        ], JSON_THROW_ON_ERROR);

        $this->expectException(AiResponseParsingFailedException::class);
        $this->expectExceptionMessage('Trait score for "leadership" is out of range [0.0, 1.0]: 1.500000');

        $this->parser->parseGenerateTraitsResponse($content, $this->availableTraits, 'test-action');
    }

    public function testParseGenerateTraitsResponseTraitScoreBelowZeroThrowsException(): void
    {
        $content = json_encode([
            'traits' => [
                'leadership' => -0.1,
            ],
            'summary' => 'Summary',
        ], JSON_THROW_ON_ERROR);

        $this->expectException(AiResponseParsingFailedException::class);
        $this->expectExceptionMessage('Trait score for "leadership" is out of range [0.0, 1.0]: -0.100000');

        $this->parser->parseGenerateTraitsResponse($content, $this->availableTraits, 'test-action');
    }

    public function testParseGenerateTraitsResponseMissingTraitKeysThrowsException(): void
    {
        $content = json_encode([
            'traits' => [
                'leadership' => 0.8,
            ],
            'summary' => 'Summary',
        ], JSON_THROW_ON_ERROR);

        $this->expectException(AiResponseParsingFailedException::class);
        $this->expectExceptionMessage('Missing trait keys in response: charisma, endurance');

        $this->parser->parseGenerateTraitsResponse($content, $this->availableTraits, 'test-action');
    }

    public function testParseGenerateTraitsResponseAcceptsIntegerScores(): void
    {
        $content = json_encode([
            'traits' => [
                'leadership' => 1,
                'charisma' => 0,
                'endurance' => 1,
            ],
            'summary' => 'Test with integer scores',
        ], JSON_THROW_ON_ERROR);

        $result = $this->parser->parseGenerateTraitsResponse($content, $this->availableTraits, 'test-action');

        self::assertSame(['leadership' => 1.0, 'charisma' => 0.0, 'endurance' => 1.0], $result->getTraitScores());
    }

    public function testParseGenerateSummaryResponseValidJson(): void
    {
        $content = json_encode([
            'summary' => 'This is a valid summary',
        ], JSON_THROW_ON_ERROR);

        $result = $this->parser->parseGenerateSummaryResponse($content, 'test-action');

        self::assertSame('This is a valid summary', $result->getSummary());
    }

    public function testParseGenerateSummaryResponseMissingSummaryThrowsException(): void
    {
        $content = json_encode([
            'other_field' => 'value',
        ], JSON_THROW_ON_ERROR);

        $this->expectException(AiResponseParsingFailedException::class);
        $this->expectExceptionMessage('Missing "summary" key in response');

        $this->parser->parseGenerateSummaryResponse($content, 'test-action');
    }

    public function testParseGenerateSummaryResponseInvalidJsonThrowsException(): void
    {
        $content = 'invalid json';

        $this->expectException(AiResponseParsingFailedException::class);
        $this->expectExceptionMessage('Invalid JSON');

        $this->parser->parseGenerateSummaryResponse($content, 'test-action');
    }

    public function testParseGenerateSummaryResponseSummaryNotStringThrowsException(): void
    {
        $content = json_encode([
            'summary' => ['not', 'a', 'string'],
        ], JSON_THROW_ON_ERROR);

        $this->expectException(AiResponseParsingFailedException::class);
        $this->expectExceptionMessage('"summary" value is not a string');

        $this->parser->parseGenerateSummaryResponse($content, 'test-action');
    }

    public function testParseGenerateBatchSummaryResponseValidJson(): void
    {
        $content = json_encode([
            'summaries' => [
                ['player_index' => 1, 'summary' => 'Strong leader.'],
                ['player_index' => 2, 'summary' => 'Empathetic player.'],
            ],
        ], JSON_THROW_ON_ERROR);

        $result = $this->parser->parseGenerateBatchSummaryResponse($content, 2, 'test-action');

        self::assertSame(['Strong leader.', 'Empathetic player.'], $result->getSummaries());
    }

    public function testParseGenerateBatchSummaryResponseSinglePlayer(): void
    {
        $content = json_encode([
            'summaries' => [
                ['player_index' => 1, 'summary' => 'Solo player.'],
            ],
        ], JSON_THROW_ON_ERROR);

        $result = $this->parser->parseGenerateBatchSummaryResponse($content, 1, 'test-action');

        self::assertSame(['Solo player.'], $result->getSummaries());
    }

    public function testParseGenerateBatchSummaryResponseReordersByPlayerIndex(): void
    {
        $content = json_encode([
            'summaries' => [
                ['player_index' => 2, 'summary' => 'Second player.'],
                ['player_index' => 1, 'summary' => 'First player.'],
            ],
        ], JSON_THROW_ON_ERROR);

        $result = $this->parser->parseGenerateBatchSummaryResponse($content, 2, 'test-action');

        self::assertSame(['First player.', 'Second player.'], $result->getSummaries());
    }

    public function testParseGenerateBatchSummaryResponseInvalidJsonThrowsException(): void
    {
        $this->expectException(AiResponseParsingFailedException::class);
        $this->expectExceptionMessage('Invalid JSON');

        $this->parser->parseGenerateBatchSummaryResponse('not valid json{', 2, 'test-action');
    }

    public function testParseGenerateBatchSummaryResponseMissingSummariesKeyThrowsException(): void
    {
        $content = json_encode(['other' => 'value'], JSON_THROW_ON_ERROR);

        $this->expectException(AiResponseParsingFailedException::class);
        $this->expectExceptionMessage('Missing "summaries" key in response');

        $this->parser->parseGenerateBatchSummaryResponse($content, 2, 'test-action');
    }

    public function testParseGenerateBatchSummaryResponseSummariesNotArrayThrowsException(): void
    {
        $content = json_encode(['summaries' => 'not-array'], JSON_THROW_ON_ERROR);

        $this->expectException(AiResponseParsingFailedException::class);
        $this->expectExceptionMessage('"summaries" value is not an array');

        $this->parser->parseGenerateBatchSummaryResponse($content, 2, 'test-action');
    }

    public function testParseGenerateBatchSummaryResponseWrongCountThrowsException(): void
    {
        $content = json_encode([
            'summaries' => [
                ['player_index' => 1, 'summary' => 'Only one.'],
            ],
        ], JSON_THROW_ON_ERROR);

        $this->expectException(AiResponseParsingFailedException::class);
        $this->expectExceptionMessage('Expected 2 summaries, got 1');

        $this->parser->parseGenerateBatchSummaryResponse($content, 2, 'test-action');
    }

    public function testParseGenerateBatchSummaryResponseMissingPlayerIndexThrowsException(): void
    {
        $content = json_encode([
            'summaries' => [
                ['summary' => 'No index.'],
            ],
        ], JSON_THROW_ON_ERROR);

        $this->expectException(AiResponseParsingFailedException::class);
        $this->expectExceptionMessage('Missing or invalid player_index at index 0');

        $this->parser->parseGenerateBatchSummaryResponse($content, 1, 'test-action');
    }

    public function testParseGenerateBatchSummaryResponseDuplicatePlayerIndexThrowsException(): void
    {
        $content = json_encode([
            'summaries' => [
                ['player_index' => 1, 'summary' => 'First.'],
                ['player_index' => 1, 'summary' => 'Duplicate.'],
            ],
        ], JSON_THROW_ON_ERROR);

        $this->expectException(AiResponseParsingFailedException::class);
        $this->expectExceptionMessage('Duplicate player_index 1');

        $this->parser->parseGenerateBatchSummaryResponse($content, 2, 'test-action');
    }

    public function testParseGenerateBatchSummaryResponseSummaryNotStringThrowsException(): void
    {
        $content = json_encode([
            'summaries' => [
                ['player_index' => 1, 'summary' => 123],
            ],
        ], JSON_THROW_ON_ERROR);

        $this->expectException(AiResponseParsingFailedException::class);
        $this->expectExceptionMessage('"summary" value at index 0 is not a string');

        $this->parser->parseGenerateBatchSummaryResponse($content, 1, 'test-action');
    }

    public function testParseGenerateBatchSummaryResponseSummaryExceedsLengthLimitThrowsException(): void
    {
        $longSummary = str_repeat('a', 201);
        $content = json_encode([
            'summaries' => [
                ['player_index' => 1, 'summary' => $longSummary],
            ],
        ], JSON_THROW_ON_ERROR);

        $this->expectException(AiResponseParsingFailedException::class);
        $this->expectExceptionMessage('Summary at index 0 exceeds 200 character limit');

        $this->parser->parseGenerateBatchSummaryResponse($content, 1, 'test-action');
    }

    public function testParseGenerateBatchSummaryResponsePlayerIndexBelowMinimumThrowsException(): void
    {
        $content = json_encode([
            'summaries' => [
                ['player_index' => 0, 'summary' => 'Invalid index.'],
            ],
        ], JSON_THROW_ON_ERROR);

        $this->expectException(AiResponseParsingFailedException::class);
        $this->expectExceptionMessage('player_index 0 out of range');

        $this->parser->parseGenerateBatchSummaryResponse($content, 1, 'test-action');
    }

    public function testParseGenerateBatchSummaryResponsePlayerIndexAboveMaximumThrowsException(): void
    {
        $content = json_encode([
            'summaries' => [
                ['player_index' => 1, 'summary' => 'Valid.'],
                ['player_index' => 3, 'summary' => 'Index too high.'],
            ],
        ], JSON_THROW_ON_ERROR);

        $this->expectException(AiResponseParsingFailedException::class);
        $this->expectExceptionMessage('player_index 3 out of range');

        $this->parser->parseGenerateBatchSummaryResponse($content, 2, 'test-action');
    }

    protected function setUp(): void
    {
        $this->parser = new AiResponseParser();

        $this->availableTraits = [
            new TraitDef('leadership', 'Leadership', 'Ability to lead others', TraitType::Social),
            new TraitDef('charisma', 'Charisma', 'Social charm and persuasion', TraitType::Social),
            new TraitDef('endurance', 'Endurance', 'Physical stamina', TraitType::Physical),
        ];
    }
}
