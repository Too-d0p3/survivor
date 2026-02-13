<?php

declare(strict_types=1);

namespace App\Tests\Functional\Domain\TraitDef;

use App\Tests\Functional\AbstractFunctionalTestCase;

final class TraitDefControllerTest extends AbstractFunctionalTestCase
{
    public function testGetTraitDefsReturnsJsonArray(): void
    {
        $this->getBrowser()->request('GET', '/api/trait-def/');

        self::assertResponseIsSuccessful();

        $responseData = json_decode(
            (string) $this->getBrowser()->getResponse()->getContent(),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        self::assertIsArray($responseData);
    }
}
