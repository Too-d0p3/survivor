<?php

declare(strict_types=1);

namespace App\Tests\Functional\Domain\User;

use App\Tests\Functional\AbstractFunctionalTestCase;
use Symfony\Component\HttpFoundation\Response;

final class AuthControllerTest extends AbstractFunctionalTestCase
{
    public function testRegisterWithValidDataReturnsSuccess(): void
    {
        $this->jsonRequest('POST', '/api/register', [
            'email' => 'newuser@example.com',
            'password' => 'password123',
        ]);

        self::assertResponseIsSuccessful();

        /** @var array{message: string} $responseData */
        $responseData = json_decode(
            (string) $this->getBrowser()->getResponse()->getContent(),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        self::assertSame('User registered successfully', $responseData['message']);
    }

    public function testRegisterWithInvalidEmailReturnsValidationError(): void
    {
        $this->jsonRequest('POST', '/api/register', [
            'email' => 'not-an-email',
            'password' => 'password123',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testRegisterWithDuplicateEmailReturns409(): void
    {
        $this->createAndPersistUser('existing@example.com', 'password123');

        $this->jsonRequest('POST', '/api/register', [
            'email' => 'existing@example.com',
            'password' => 'password123',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
    }
}
