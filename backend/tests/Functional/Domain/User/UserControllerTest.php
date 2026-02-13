<?php

declare(strict_types=1);

namespace App\Tests\Functional\Domain\User;

use App\Tests\Functional\AbstractFunctionalTestCase;
use Symfony\Component\HttpFoundation\Response;

final class UserControllerTest extends AbstractFunctionalTestCase
{
    public function testMeWithoutAuthReturns401(): void
    {
        $this->getBrowser()->request('GET', '/api/user/me');

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
