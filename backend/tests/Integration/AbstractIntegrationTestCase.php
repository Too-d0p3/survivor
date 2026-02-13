<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Domain\User\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

abstract class AbstractIntegrationTestCase extends KernelTestCase
{
    protected function setUp(): void
    {
        self::bootKernel();
    }

    protected function getEntityManager(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }

    /**
     * @template T of object
     * @param class-string<T> $serviceClass
     * @return T
     */
    protected function getService(string $serviceClass): object
    {
        $service = self::getContainer()->get($serviceClass);
        self::assertInstanceOf($serviceClass, $service);

        return $service;
    }

    protected function createAndPersistUser(string $email = 'test@example.com', string $password = 'password123'): User
    {
        $user = new User($email);
        $passwordHasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        $hashedPassword = $passwordHasher->hashPassword($user, $password);
        $user->setPassword($hashedPassword);

        $entityManager = $this->getEntityManager();
        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }
}
