<?php

declare(strict_types=1);

namespace App\Domain\Game;

use App\Domain\User\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class GameController extends AbstractController
{
    private readonly GameFacade $gameFacade;

    public function __construct(GameFacade $gameFacade)
    {
        $this->gameFacade = $gameFacade;
    }

    #[Route('/api/game/create', name: 'game_create', methods: ['POST'])]
    public function createGame(#[CurrentUser] ?User $user): JsonResponse
    {
        if ($user === null) {
            return $this->json(['message' => 'Not authenticated'], 401);
        }

        $game = $this->gameFacade->createGameForUser($user);

        return $this->json([
            'id' => $game->getId(),
            'message' => 'Hra vytvořena.',
        ]);
    }
}
