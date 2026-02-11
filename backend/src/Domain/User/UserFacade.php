<?php

declare(strict_types=1);

namespace App\Domain\User;

use App\Domain\User\Exceptions\CannotRegisterUserBecauseUserWithSameEmailAlreadyExistsException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class UserFacade
{
    private readonly UserRepository $userRepository;

    private readonly UserPasswordHasherInterface $passwordHasher;

    private readonly EntityManagerInterface $entityManager;

    public function __construct(
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
    ) {
        $this->userRepository = $userRepository;
        $this->passwordHasher = $passwordHasher;
        $this->entityManager = $entityManager;
    }

    public function registerUser(
        string $email,
        string $password,
    ): User {
        if ($this->userRepository->findOneBy(['email' => $email]) !== null) {
            throw new CannotRegisterUserBecauseUserWithSameEmailAlreadyExistsException($email);
        }

        $user = new User($email);
        $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
        $user->setPassword($hashedPassword);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }
}
