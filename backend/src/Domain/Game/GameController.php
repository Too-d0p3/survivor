<?php

declare(strict_types=1);

namespace App\Domain\Game;

use App\Domain\User\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

class GameController extends AbstractController
{
    #[Route('/api/game/create', name: 'game_create', methods: ['POST'])]
    public function createGame(GameService $gameService, Security $security, #[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return $this->json(['message' => 'Not authenticated'], 401);
        }

        $game = $gameService->createGameForUser($user);

        return $this->json([
            'id' => $game->getId(),
            'message' => 'Hra vytvořena.',
        ]);
    }
}
