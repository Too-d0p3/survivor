<?php

declare(strict_types=1);

namespace App\Domain\Player;

use App\Domain\Ai\AiPlayerFacade;
use App\Domain\TraitDef\TraitDefRepository;
use App\Domain\User\User;
use App\Dto\Game\Player\GenerateTraitsInput;
use App\Shared\Controller\AbstractApiController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class PlayerController extends AbstractApiController
{
    private readonly AiPlayerFacade $aiPlayerFacade;

    private readonly TraitDefRepository $traitDefRepository;

    public function __construct(
        AiPlayerFacade $aiPlayerFacade,
        TraitDefRepository $traitDefRepository,
    ) {
        $this->aiPlayerFacade = $aiPlayerFacade;
        $this->traitDefRepository = $traitDefRepository;
    }

    #[Route('/api/game/player/traits/generate', name: 'game_player_traits_generate', methods: ['POST'])]
    public function generateTraits(
        #[CurrentUser] ?User $user,
        Request $request,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
    ): JsonResponse {
        if ($user === null) {
            return $this->json(['message' => 'Not authenticated'], 401);
        }

        $validationResult = $this->getValidatedDto($request, GenerateTraitsInput::class, $serializer, $validator);

        if (!$validationResult->isValid()) {
            return $this->json(['errors' => $validationResult->errors], 400);
        }

        assert($validationResult->dto instanceof GenerateTraitsInput);

        $traits = array_values($this->traitDefRepository->findAll());
        $result = $this->aiPlayerFacade->generatePlayerTraitsFromDescription($validationResult->dto->description, $traits);

        return $this->json($result);
    }

    #[Route(
        '/api/game/player/traits/generate-summary-description',
        name: 'game_player_traits_generate_summary_description',
        methods: ['POST'],
    )]
    public function generateSummaryDescription(
        #[CurrentUser] ?User $user,
        Request $request,
    ): JsonResponse {
        if ($user === null) {
            return $this->json(['message' => 'Not authenticated'], 401);
        }

        /** @var array<string, string> $traitStrengths */
        $traitStrengths = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $result = $this->aiPlayerFacade->generatePlayerTraitsSummaryDescription($traitStrengths);

        return $this->json($result);
    }
}
