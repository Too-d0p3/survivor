<?php

declare(strict_types=1);

namespace App\Tests\Integration\Domain\TraitDef;

use App\Domain\TraitDef\Exceptions\CannotCreateTraitDefBecauseTraitDefWithSameKeyAlreadyExistsException;
use App\Domain\TraitDef\TraitDef;
use App\Domain\TraitDef\TraitDefFacade;
use App\Domain\TraitDef\TraitType;
use App\Tests\Integration\AbstractIntegrationTestCase;

final class TraitDefFacadeTest extends AbstractIntegrationTestCase
{
    private TraitDefFacade $traitDefFacade;

    public function testGetAllReturnsEmptyArrayWhenNoTraitDefs(): void
    {
        $result = $this->traitDefFacade->getAll();

        self::assertSame([], $result);
    }

    public function testCreateTraitDefPersistsInDatabase(): void
    {
        $traitDef = $this->traitDefFacade->createTraitDef(
            'charisma',
            'Charisma',
            'Social charm and persuasion',
            TraitType::Social,
        );

        $foundTraitDef = $this->getEntityManager()->find(TraitDef::class, $traitDef->getId());
        self::assertNotNull($foundTraitDef);
    }

    public function testCreateTraitDefSetsAllProperties(): void
    {
        $traitDef = $this->traitDefFacade->createTraitDef(
            'endurance',
            'Endurance',
            'Physical stamina and resilience',
            TraitType::Physical,
        );

        self::assertSame('endurance', $traitDef->getKey());
        self::assertSame('Endurance', $traitDef->getLabel());
        self::assertSame('Physical stamina and resilience', $traitDef->getDescription());
        self::assertSame(TraitType::Physical, $traitDef->getType());
    }

    public function testCreateTraitDefWithDuplicateKeyThrowsException(): void
    {
        $this->traitDefFacade->createTraitDef(
            'leadership',
            'Leadership',
            'Ability to lead others',
            TraitType::Strategic,
        );

        $this->expectException(CannotCreateTraitDefBecauseTraitDefWithSameKeyAlreadyExistsException::class);

        $this->traitDefFacade->createTraitDef(
            'leadership',
            'Leadership 2',
            'Another description',
            TraitType::Strategic,
        );
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->traitDefFacade = $this->getService(TraitDefFacade::class);
    }
}
