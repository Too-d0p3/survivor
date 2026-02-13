<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Ai\Dto;

use App\Domain\Ai\Dto\AiResponseSchema;
use PHPUnit\Framework\TestCase;

final class AiResponseSchemaTest extends TestCase
{
    public function testConstructorSetsAllProperties(): void
    {
        $properties = [
            'name' => ['type' => 'string'],
            'age' => ['type' => 'integer'],
        ];
        $required = ['name', 'age'];

        $schema = new AiResponseSchema('object', $properties, $required, 'Test schema description');

        self::assertSame('object', $schema->getType());
        self::assertSame($properties, $schema->getProperties());
        self::assertSame($required, $schema->getRequired());
        self::assertSame('Test schema description', $schema->getDescription());
    }

    public function testConstructorWithNullDescription(): void
    {
        $properties = ['field' => ['type' => 'string']];
        $required = ['field'];

        $schema = new AiResponseSchema('object', $properties, $required);

        self::assertNull($schema->getDescription());
    }

    public function testToArrayIncludesAllFieldsWithDescription(): void
    {
        $properties = [
            'trait_scores' => ['type' => 'object'],
            'summary' => ['type' => 'string'],
        ];
        $required = ['trait_scores', 'summary'];

        $schema = new AiResponseSchema('object', $properties, $required, 'Personality traits');

        $result = $schema->toArray();

        self::assertSame('object', $result['type']);
        self::assertSame($properties, $result['properties']);
        self::assertSame($required, $result['required']);
        self::assertSame('Personality traits', $result['description']);
        self::assertCount(4, $result);
    }

    public function testToArrayExcludesDescriptionWhenNull(): void
    {
        $properties = ['field' => ['type' => 'string']];
        $required = ['field'];

        $schema = new AiResponseSchema('object', $properties, $required);

        $result = $schema->toArray();

        self::assertSame('object', $result['type']);
        self::assertSame($properties, $result['properties']);
        self::assertSame($required, $result['required']);
        self::assertArrayNotHasKey('description', $result);
        self::assertCount(3, $result);
    }

    public function testToArrayWithEmptyPropertiesAndRequired(): void
    {
        $schema = new AiResponseSchema('object', [], []);

        $result = $schema->toArray();

        self::assertSame('object', $result['type']);
        self::assertSame([], $result['properties']);
        self::assertSame([], $result['required']);
    }
}
