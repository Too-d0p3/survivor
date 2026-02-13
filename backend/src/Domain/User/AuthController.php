<?php

declare(strict_types=1);

namespace App\Domain\User;

use App\Domain\User\Exceptions\CannotRegisterUserBecauseUserWithSameEmailAlreadyExistsException;
use App\Dto\RegisterInput;
use App\Shared\Controller\AbstractApiController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class AuthController extends AbstractApiController
{
    private readonly UserFacade $userFacade;

    public function __construct(UserFacade $userFacade)
    {
        $this->userFacade = $userFacade;
    }

    #[Route('/api/register', name: 'api_register', methods: ['POST'])]
    public function register(
        Request $request,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
    ): JsonResponse {
        $validationResult = $this->getValidatedDto($request, RegisterInput::class, $serializer, $validator);

        if (!$validationResult->isValid()) {
            return $this->json(['errors' => $validationResult->errors], 400);
        }

        assert($validationResult->dto instanceof RegisterInput);

        try {
            $this->userFacade->registerUser($validationResult->dto->email, $validationResult->dto->password);
        } catch (CannotRegisterUserBecauseUserWithSameEmailAlreadyExistsException) {
            return $this->json(['error' => 'User with this email already exists'], 409);
        }

        return $this->json(['message' => 'User registered successfully']);
    }
}
