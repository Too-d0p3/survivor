<?php

declare(strict_types=1);

namespace App\Domain\Game;

use App\Domain\User\User;
use App\Dto\Game\CreateGameInput;
use App\Shared\Controller\AbstractApiController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class GameController extends AbstractApiController
{
    private readonly GameFacade $gameFacade;

    public function __construct(GameFacade $gameFacade)
    {
        $this->gameFacade = $gameFacade;
    }

    #[Route('/api/game/create', name: 'game_create', methods: ['POST'])]
    public function createGame(
        #[CurrentUser] ?User $user,
        Request $request,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
    ): JsonResponse {
        if ($user === null) {
            return $this->json(['message' => 'Not authenticated'], 401);
        }

        $validationResult = $this->getValidatedDto($request, CreateGameInput::class, $serializer, $validator);

        if (!$validationResult->isValid()) {
            return $this->json(['errors' => $validationResult->errors], 400);
        }

        assert($validationResult->dto instanceof CreateGameInput);

        $result = $this->gameFacade->createGame(
            $user,
            $validationResult->dto->playerName,
            $validationResult->dto->playerDescription,
            $validationResult->dto->traitStrengths,
        );

        $game = $result->game;

        $players = [];
        foreach ($game->getPlayers() as $player) {
            $traits = [];
            foreach ($player->getPlayerTraits() as $playerTrait) {
                $traits[$playerTrait->getTraitDef()->getKey()] = $playerTrait->getStrength();
            }

            $players[] = [
                'id' => $player->getId()->toString(),
                'name' => $player->getName(),
                'isHuman' => $player->isHuman(),
                'description' => $player->getDescription(),
                'traits' => $traits,
            ];
        }

        return $this->json([
            'id' => $game->getId()->toString(),
            'players' => $players,
        ]);
    }
}
