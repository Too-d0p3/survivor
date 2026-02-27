<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Ai\Operation;

use App\Domain\Ai\Exceptions\AiResponseParsingFailedException;
use App\Domain\Ai\Operation\InitializeRelationshipsOperation;
use App\Domain\Ai\Operation\PlayerRelationshipInput;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class InitializeRelationshipsOperationTest extends TestCase
{
    public function testGetActionName(): void
    {
        $operation = $this->createOperation(2);

        self::assertSame('initializeRelationships', $operation->getActionName());
    }

    public function testGetTemplateName(): void
    {
        $operation = $this->createOperation(2);

        self::assertSame('initialize_relationships', $operation->getTemplateName());
    }

    public function testGetTemplateVariablesReturnsEmptyArray(): void
    {
        $operation = $this->createOperation(2);

        self::assertSame([], $operation->getTemplateVariables());
    }

    public function testGetTemperatureReturns09(): void
    {
        $operation = $this->createOperation(2);

        self::assertSame(0.9, $operation->getTemperature());
    }

    public function testGetResponseSchemaHasRelationshipsKey(): void
    {
        $operation = $this->createOperation(2);
        $schema = $operation->getResponseSchema();

        self::assertSame('object', $schema->getType());
        self::assertSame(['relationships'], $schema->getRequired());
    }

    public function testGetMessagesReturnsSingleUserMessage(): void
    {
        $operation = $this->createOperation(2);
        $messages = $operation->getMessages();

        self::assertCount(1, $messages);
        self::assertSame('user', $messages[0]->getRole());
    }

    public function testFormatMessageIncludesAllPlayerData(): void
    {
        $players = [
            new PlayerRelationshipInput('Alice', 'A strong leader', ['leadership' => '0.85', 'empathy' => '0.60']),
            new PlayerRelationshipInput('Bob', 'A quiet observer', ['leadership' => '0.30']),
        ];

        $operation = new InitializeRelationshipsOperation($players);
        $messages = $operation->getMessages();

        $content = $messages[0]->getContent();
        self::assertStringContainsString('Hráč 1: Alice', $content);
        self::assertStringContainsString('Popis: A strong leader', $content);
        self::assertStringContainsString('- leadership: 0.85', $content);
        self::assertStringContainsString('- empathy: 0.60', $content);
        self::assertStringContainsString('Hráč 2: Bob', $content);
        self::assertStringContainsString('Popis: A quiet observer', $content);
    }

    public function testConstructorThrowsWhenLessThanTwoPlayers(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('At least 2 players are required');

        new InitializeRelationshipsOperation([
            new PlayerRelationshipInput('Alice', 'Description', ['leadership' => '0.85']),
        ]);
    }

    public function testConstructorThrowsWhenPlayerHasEmptyName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Player at index 0 has an empty name');

        new InitializeRelationshipsOperation([
            new PlayerRelationshipInput('', 'Description', ['leadership' => '0.85']),
            new PlayerRelationshipInput('Bob', 'Description', ['leadership' => '0.85']),
        ]);
    }

    public function testConstructorThrowsWhenPlayerHasEmptyDescription(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Player at index 0 has an empty description');

        new InitializeRelationshipsOperation([
            new PlayerRelationshipInput('Alice', '', ['leadership' => '0.85']),
            new PlayerRelationshipInput('Bob', 'Description', ['leadership' => '0.85']),
        ]);
    }

    public function testConstructorThrowsWhenPlayerHasNoTraits(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Player at index 0 has no trait strengths');

        new InitializeRelationshipsOperation([
            new PlayerRelationshipInput('Alice', 'Description', []),
            new PlayerRelationshipInput('Bob', 'Description', ['leadership' => '0.85']),
        ]);
    }

    public function testParseValidResponseReturnsResult(): void
    {
        $operation = $this->createOperation(2);

        $content = json_encode([
            'relationships' => [
                ['source_index' => 1, 'target_index' => 2, 'trust' => 60, 'affinity' => 70, 'respect' => 55, 'threat' => 30],
                ['source_index' => 2, 'target_index' => 1, 'trust' => 40, 'affinity' => 45, 'respect' => 65, 'threat' => 80],
            ],
        ], JSON_THROW_ON_ERROR);

        $result = $operation->parse($content);
        $relationships = $result->getRelationships();

        self::assertCount(2, $relationships);
        self::assertSame(1, $relationships[0]->sourceIndex);
        self::assertSame(2, $relationships[0]->targetIndex);
        self::assertSame(60, $relationships[0]->trust);
        self::assertSame(70, $relationships[0]->affinity);
        self::assertSame(55, $relationships[0]->respect);
        self::assertSame(30, $relationships[0]->threat);
    }

    public function testParseInvalidJsonThrowsException(): void
    {
        $operation = $this->createOperation(2);

        $this->expectException(AiResponseParsingFailedException::class);
        $this->expectExceptionMessage('Invalid JSON');

        $operation->parse('not valid json{');
    }

    public function testParseMissingRelationshipsKeyThrowsException(): void
    {
        $operation = $this->createOperation(2);

        $this->expectException(AiResponseParsingFailedException::class);
        $this->expectExceptionMessage('Missing "relationships" key in response');

        $operation->parse(json_encode(['other' => 'value'], JSON_THROW_ON_ERROR));
    }

    public function testParseWrongCountThrowsException(): void
    {
        $operation = $this->createOperation(3);

        $content = json_encode([
            'relationships' => [
                ['source_index' => 1, 'target_index' => 2, 'trust' => 50, 'affinity' => 50, 'respect' => 50, 'threat' => 50],
            ],
        ], JSON_THROW_ON_ERROR);

        $this->expectException(AiResponseParsingFailedException::class);
        $this->expectExceptionMessage('Expected 6 relationships for 3 players, got 1');

        $operation->parse($content);
    }

    public function testParseSelfRelationshipThrowsException(): void
    {
        $operation = $this->createOperation(2);

        $content = json_encode([
            'relationships' => [
                ['source_index' => 1, 'target_index' => 1, 'trust' => 50, 'affinity' => 50, 'respect' => 50, 'threat' => 50],
                ['source_index' => 2, 'target_index' => 1, 'trust' => 50, 'affinity' => 50, 'respect' => 50, 'threat' => 50],
            ],
        ], JSON_THROW_ON_ERROR);

        $this->expectException(AiResponseParsingFailedException::class);
        $this->expectExceptionMessage('Self-relationship at index 0');

        $operation->parse($content);
    }

    public function testParseDuplicatePairThrowsException(): void
    {
        $operation = $this->createOperation(2);

        $content = json_encode([
            'relationships' => [
                ['source_index' => 1, 'target_index' => 2, 'trust' => 50, 'affinity' => 50, 'respect' => 50, 'threat' => 50],
                ['source_index' => 1, 'target_index' => 2, 'trust' => 60, 'affinity' => 60, 'respect' => 60, 'threat' => 60],
            ],
        ], JSON_THROW_ON_ERROR);

        $this->expectException(AiResponseParsingFailedException::class);
        $this->expectExceptionMessage('Duplicate pair 1:2 at index 1');

        $operation->parse($content);
    }

    public function testParseOutOfRangeIndexThrowsException(): void
    {
        $operation = $this->createOperation(2);

        $content = json_encode([
            'relationships' => [
                ['source_index' => 0, 'target_index' => 2, 'trust' => 50, 'affinity' => 50, 'respect' => 50, 'threat' => 50],
                ['source_index' => 2, 'target_index' => 1, 'trust' => 50, 'affinity' => 50, 'respect' => 50, 'threat' => 50],
            ],
        ], JSON_THROW_ON_ERROR);

        $this->expectException(AiResponseParsingFailedException::class);
        $this->expectExceptionMessage('source_index value 0 is out of range');

        $operation->parse($content);
    }

    public function testParseOutOfRangeScoreThrowsException(): void
    {
        $operation = $this->createOperation(2);

        $content = json_encode([
            'relationships' => [
                ['source_index' => 1, 'target_index' => 2, 'trust' => 101, 'affinity' => 50, 'respect' => 50, 'threat' => 50],
                ['source_index' => 2, 'target_index' => 1, 'trust' => 50, 'affinity' => 50, 'respect' => 50, 'threat' => 50],
            ],
        ], JSON_THROW_ON_ERROR);

        $this->expectException(AiResponseParsingFailedException::class);
        $this->expectExceptionMessage('"trust" value 101 at relationship index 0 is out of range [0, 100]');

        $operation->parse($content);
    }

    public function testParseMissingScoreFieldThrowsException(): void
    {
        $operation = $this->createOperation(2);

        $content = json_encode([
            'relationships' => [
                ['source_index' => 1, 'target_index' => 2, 'trust' => 50, 'respect' => 50, 'threat' => 50],
                ['source_index' => 2, 'target_index' => 1, 'trust' => 50, 'affinity' => 50, 'respect' => 50, 'threat' => 50],
            ],
        ], JSON_THROW_ON_ERROR);

        $this->expectException(AiResponseParsingFailedException::class);
        $this->expectExceptionMessage('Missing "affinity" at relationship index 0');

        $operation->parse($content);
    }

    public function testParseMissingSourceIndexThrowsException(): void
    {
        $operation = $this->createOperation(2);

        $content = json_encode([
            'relationships' => [
                ['target_index' => 2, 'trust' => 50, 'affinity' => 50, 'respect' => 50, 'threat' => 50],
                ['source_index' => 2, 'target_index' => 1, 'trust' => 50, 'affinity' => 50, 'respect' => 50, 'threat' => 50],
            ],
        ], JSON_THROW_ON_ERROR);

        $this->expectException(AiResponseParsingFailedException::class);
        $this->expectExceptionMessage('Missing or non-integer "source_index" at relationship index 0');

        $operation->parse($content);
    }

    private function createOperation(int $playerCount): InitializeRelationshipsOperation
    {
        $names = ['Alice', 'Bob', 'Charlie', 'Dana', 'Emil', 'Fiona'];
        $players = [];

        for ($i = 0; $i < $playerCount; $i++) {
            $players[] = new PlayerRelationshipInput(
                $names[$i],
                sprintf('Description for %s', $names[$i]),
                ['leadership' => '0.85', 'empathy' => '0.60'],
            );
        }

        return new InitializeRelationshipsOperation($players);
    }
}
