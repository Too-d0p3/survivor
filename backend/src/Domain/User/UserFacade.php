<?php

namespace App\Domain\User;

use App\Domain\User\Exceptions\CannotRegisterUserBecauseUserWithSameEmailAlreadyExistsException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFacade
{

    public function __construct(
        private UserRepository $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private EntityManagerInterface $entityManager,
    ) {

    }

    public function registerUser(
        string $email,
        string $password,
    ): User
    {
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