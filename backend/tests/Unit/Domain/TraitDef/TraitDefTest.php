<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\TraitDef;

use App\Domain\TraitDef\TraitDef;
use App\Domain\TraitDef\TraitType;
use PHPUnit\Framework\TestCase;

final class TraitDefTest extends TestCase
{
    public function testConstructorSetsAllProperties(): void
    {
        $traitDef = new TraitDef('charisma', 'Charisma', 'Social charm and persuasion', TraitType::Social);

        self::assertNotEmpty($traitDef->getId()->toRfc4122());
        self::assertSame('charisma', $traitDef->getKey());
        self::assertSame('Charisma', $traitDef->getLabel());
        self::assertSame('Social charm and persuasion', $traitDef->getDescription());
        self::assertSame(TraitType::Social, $traitDef->getType());
    }

    public function testGetTypeReturnsCorrectEnum(): void
    {
        $traitDef = new TraitDef('endurance', 'Endurance', 'Physical stamina', TraitType::Physical);

        self::assertSame(TraitType::Physical, $traitDef->getType());
    }
}
