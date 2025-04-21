<?php

namespace App\Controller\Game;

use App\Controller\AbstractApiController;
use App\Dto\Game\Player\GenerateTraitsInput;
use App\Entity\PlayerTrait;
use App\Entity\User;
use App\Repository\TraitDefRepository;
use App\Service\Game\Player\PlayerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class PlayerController extends AbstractApiController
{
    public function __construct(private PlayerService $playerService) {}

    #[Route('/api/game/player/traits/generate', name: 'game_player_traits_generate', methods: ['POST'])]
    public function generateTraits(
        #[CurrentUser] ?User $user,
        Request $request,
        SerializerInterface $serializer,
        ValidatorInterface $validator
    ): JsonResponse {
        if (!$user) {
            return $this->json(['message' => 'Not authenticated'], 401);
        }

        [$input, $errors] = $this->getValidatedDto($request, GenerateTraitsInput::class, $serializer, $validator);

        if ($errors) {
            return $this->json(['errors' => $errors], 400);
        }

        $result = $this->playerService->generatePlayerTraitsFromDescription($input->description);

        return $this->json($result);
    }

    #[Route('/api/game/player/traits/generate-summary-description', name: 'game_player_traits_generate_summary_description', methods: ['POST'])]
    public function generateSummaryDescription(
        #[CurrentUser] ?User $user,
        Request $request,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        TraitDefRepository $traitDefRepository,
    ): JsonResponse {
        if (!$user) {
            return $this->json(['message' => 'Not authenticated'], 401);
        }

        $userTraits = json_decode($request->getContent(), true);

        $traits = $traitDefRepository->findAll();

        $playerTraits = [];

        foreach ($traits as $trait) {
            $playerTrait = new PlayerTrait();
            $playerTrait->setTraitDef($trait);
            $playerTrait->setStrength($userTraits[$trait->getKey()] ?? 0);
            $playerTraits[] = $playerTrait;
        }

        $result = $this->playerService->generatePlayerTraitsSummaryDescription($playerTraits);

        return $this->json($result);
    }
}
