<?php

declare(strict_types=1);

namespace App\Domain\User;

use App\Domain\User\Exceptions\CannotRegisterUserBecauseUserWithSameEmailAlreadyExistsException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFacade
{
    private UserRepository $userRepository;

    private UserPasswordHasherInterface $passwordHasher;

    private EntityManagerInterface $entityManager;

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
        if ($this->userRepository->findOneBy(['email' => $email])) {
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
