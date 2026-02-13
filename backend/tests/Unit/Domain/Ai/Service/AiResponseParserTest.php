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
            ],
            'summary' => 'Strong leader with good social skills',
        ], JSON_THROW_ON_ERROR);

        $result = $this->parser->parseGenerateTraitsResponse($content, $this->availableTraits, 'test-action');

        self::assertSame(['leadership' => 0.8, 'charisma' => 0.6], $result->getTraitScores());
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

    public function testParseGenerateTraitsResponseAcceptsIntegerScores(): void
    {
        $content = json_encode([
            'traits' => [
                'leadership' => 1,
                'charisma' => 0,
            ],
            'summary' => 'Test with integer scores',
        ], JSON_THROW_ON_ERROR);

        $result = $this->parser->parseGenerateTraitsResponse($content, $this->availableTraits, 'test-action');

        self::assertSame(['leadership' => 1.0, 'charisma' => 0.0], $result->getTraitScores());
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
