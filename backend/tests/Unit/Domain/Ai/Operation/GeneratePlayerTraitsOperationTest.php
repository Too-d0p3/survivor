<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Ai\Operation;

use App\Domain\Ai\Exceptions\AiResponseParsingFailedException;
use App\Domain\Ai\Operation\GeneratePlayerTraitsOperation;
use App\Domain\TraitDef\TraitDef;
use App\Domain\TraitDef\TraitType;
use PHPUnit\Framework\TestCase;

final class GeneratePlayerTraitsOperationTest extends TestCase
{
    /** @var array<int, TraitDef> */
    private array $traits;

    public function testGetActionName(): void
    {
        $operation = new GeneratePlayerTraitsOperation('A strong leader', $this->traits);

        self::assertSame('generatePlayerTraitsFromDescription', $operation->getActionName());
    }

    public function testGetTemplateName(): void
    {
        $operation = new GeneratePlayerTraitsOperation('A strong leader', $this->traits);

        self::assertSame('generate_player_traits', $operation->getTemplateName());
    }

    public function testGetTemplateVariables(): void
    {
        $operation = new GeneratePlayerTraitsOperation('A strong leader', $this->traits);

        $expected = "* leadership\n* charisma\n* endurance";
        self::assertSame(['traitKeys' => $expected], $operation->getTemplateVariables());
    }

    public function testGetMessages(): void
    {
        $operation = new GeneratePlayerTraitsOperation('A strong leader', $this->traits);
        $messages = $operation->getMessages();

        self::assertCount(1, $messages);
        self::assertSame('A strong leader', $messages[0]->getContent());
        self::assertSame('user', $messages[0]->getRole());
    }

    public function testGetResponseSchema(): void
    {
        $operation = new GeneratePlayerTraitsOperation('A strong leader', $this->traits);
        $schema = $operation->getResponseSchema();

        self::assertSame('object', $schema->getType());
        self::assertSame(['traits', 'summary'], $schema->getRequired());

        $properties = $schema->getProperties();
        self::assertArrayHasKey('traits', $properties);
        self::assertArrayHasKey('summary', $properties);
    }

    public function testGetTemperatureReturnsNull(): void
    {
        $operation = new GeneratePlayerTraitsOperation('A strong leader', $this->traits);

        self::assertNull($operation->getTemperature());
    }

    public function testParseValidJson(): void
    {
        $operation = new GeneratePlayerTraitsOperation('A strong leader', $this->traits);

        $content = json_encode([
            'traits' => [
                'leadership' => 0.8,
                'charisma' => 0.6,
                'endurance' => 0.7,
            ],
            'summary' => 'Strong leader with good social skills',
        ], JSON_THROW_ON_ERROR);

        $result = $operation->parse($content);

        self::assertSame(['leadership' => 0.8, 'charisma' => 0.6, 'endurance' => 0.7], $result->getTraitScores());
        self::assertSame('Strong leader with good social skills', $result->getSummary());
    }

    public function testParseMissingTraitsKeyThrowsException(): void
    {
        $operation = new GeneratePlayerTraitsOperation('A strong leader', $this->traits);

        $content = json_encode([
            'summary' => 'Summary without traits',
        ], JSON_THROW_ON_ERROR);

        $this->expectException(AiResponseParsingFailedException::class);
        $this->expectExceptionMessage('Missing "traits" key in response');

        $operation->parse($content);
    }

    public function testParseMissingSummaryKeyThrowsException(): void
    {
        $operation = new GeneratePlayerTraitsOperation('A strong leader', $this->traits);

        $content = json_encode([
            'traits' => ['leadership' => 0.8],
        ], JSON_THROW_ON_ERROR);

        $this->expectException(AiResponseParsingFailedException::class);
        $this->expectExceptionMessage('Missing "summary" key in response');

        $operation->parse($content);
    }

    public function testParseInvalidJsonThrowsException(): void
    {
        $operation = new GeneratePlayerTraitsOperation('A strong leader', $this->traits);

        $this->expectException(AiResponseParsingFailedException::class);
        $this->expectExceptionMessage('Invalid JSON');

        $operation->parse('not valid json{');
    }

    public function testParseUnknownTraitKeyThrowsException(): void
    {
        $operation = new GeneratePlayerTraitsOperation('A strong leader', $this->traits);

        $content = json_encode([
            'traits' => ['unknown-trait' => 0.5],
            'summary' => 'Summary',
        ], JSON_THROW_ON_ERROR);

        $this->expectException(AiResponseParsingFailedException::class);
        $this->expectExceptionMessage('Unknown trait key "unknown-trait" in response');

        $operation->parse($content);
    }

    public function testParseTraitScoreOutOfRangeThrowsException(): void
    {
        $operation = new GeneratePlayerTraitsOperation('A strong leader', $this->traits);

        $content = json_encode([
            'traits' => ['leadership' => 1.5],
            'summary' => 'Summary',
        ], JSON_THROW_ON_ERROR);

        $this->expectException(AiResponseParsingFailedException::class);
        $this->expectExceptionMessage('Trait score for "leadership" is out of range [0.0, 1.0]: 1.500000');

        $operation->parse($content);
    }

    public function testParseTraitScoreBelowZeroThrowsException(): void
    {
        $operation = new GeneratePlayerTraitsOperation('A strong leader', $this->traits);

        $content = json_encode([
            'traits' => ['leadership' => -0.1],
            'summary' => 'Summary',
        ], JSON_THROW_ON_ERROR);

        $this->expectException(AiResponseParsingFailedException::class);
        $this->expectExceptionMessage('Trait score for "leadership" is out of range [0.0, 1.0]: -0.100000');

        $operation->parse($content);
    }

    public function testParseMissingTraitKeysThrowsException(): void
    {
        $operation = new GeneratePlayerTraitsOperation('A strong leader', $this->traits);

        $content = json_encode([
            'traits' => ['leadership' => 0.8],
            'summary' => 'Summary',
        ], JSON_THROW_ON_ERROR);

        $this->expectException(AiResponseParsingFailedException::class);
        $this->expectExceptionMessage('Missing trait keys in response: charisma, endurance');

        $operation->parse($content);
    }

    public function testParseAcceptsIntegerScores(): void
    {
        $operation = new GeneratePlayerTraitsOperation('A strong leader', $this->traits);

        $content = json_encode([
            'traits' => [
                'leadership' => 1,
                'charisma' => 0,
                'endurance' => 1,
            ],
            'summary' => 'Test with integer scores',
        ], JSON_THROW_ON_ERROR);

        $result = $operation->parse($content);

        self::assertSame(['leadership' => 1.0, 'charisma' => 0.0, 'endurance' => 1.0], $result->getTraitScores());
    }

    protected function setUp(): void
    {
        $this->traits = [
            new TraitDef('leadership', 'Leadership', 'Ability to lead others', TraitType::Social),
            new TraitDef('charisma', 'Charisma', 'Social charm and persuasion', TraitType::Social),
            new TraitDef('endurance', 'Endurance', 'Physical stamina', TraitType::Physical),
        ];
    }
}
