<?php

declare(strict_types=1);

namespace App\Tests\Functional\Domain\Game;

use App\Tests\Functional\AbstractFunctionalTestCase;
use Symfony\Component\HttpFoundation\Response;

final class GameControllerTest extends AbstractFunctionalTestCase
{
    public function testCreateGameWithoutAuthReturns401(): void
    {
        $this->jsonRequest('POST', '/api/game/create');

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
