<?php

namespace App\Domain\User;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/user', name: 'user_')]
class UserController extends AbstractController
{
    #[Route('/me', name: 'me', methods: ['GET'])]
    public function me(#[CurrentUser] ?User $user): \Symfony\Component\HttpFoundation\JsonResponse
    {
        if (!$user) {
            return $this->json(['message' => 'Not authenticated'], 401);
        }

        return $this->json($user, 200, [], ['groups' => 'user:read']);
    }
}