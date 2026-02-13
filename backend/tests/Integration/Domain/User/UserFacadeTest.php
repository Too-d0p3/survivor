<?php

declare(strict_types=1);

namespace App\Tests\Integration\Domain\User;

use App\Domain\User\Exceptions\CannotRegisterUserBecauseUserWithSameEmailAlreadyExistsException;
use App\Domain\User\User;
use App\Domain\User\UserFacade;
use App\Tests\Integration\AbstractIntegrationTestCase;

final class UserFacadeTest extends AbstractIntegrationTestCase
{
    private UserFacade $userFacade;

    public function testRegisterUserPersistsAndHashesPassword(): void
    {
        $user = $this->userFacade->registerUser('new@example.com', 'password123');

        self::assertNotSame('password123', $user->getPassword());
        self::assertNotEmpty($user->getPassword());

        $foundUser = $this->getEntityManager()->find(User::class, $user->getId());
        self::assertNotNull($foundUser);
    }

    public function testRegisterUserSetsEmail(): void
    {
        $user = $this->userFacade->registerUser('registered@example.com', 'password123');

        self::assertSame('registered@example.com', $user->getEmail());
    }

    public function testRegisterUserWithDuplicateEmailThrowsException(): void
    {
        $this->userFacade->registerUser('duplicate@example.com', 'password123');

        $this->expectException(CannotRegisterUserBecauseUserWithSameEmailAlreadyExistsException::class);

        $this->userFacade->registerUser('duplicate@example.com', 'other_password');
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->userFacade = $this->getService(UserFacade::class);
    }
}
