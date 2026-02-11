<?php

declare(strict_types=1);

namespace App\Domain\User;

use App\Domain\User\Exceptions\CannotRegisterUserBecauseUserWithSameEmailAlreadyExistsException;
use App\Dto\RegisterInput;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsController]
class AuthController
{
    private UserFacade $userFacade;

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
        $data = $request->getContent();

        /** @var RegisterInput $input */
        $input = $serializer->deserialize($data, RegisterInput::class, 'json');

        $errors = $validator->validate($input);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getPropertyPath() . ': ' . $error->getMessage();
            }

            return new JsonResponse(['errors' => $errorMessages], 400);
        }

        try {
            $this->userFacade->registerUser($input->getEmail(), $input->getPassword());
        } catch (CannotRegisterUserBecauseUserWithSameEmailAlreadyExistsException $e) {
            return new JsonResponse(['error' => 'User with this email already exists'], 409);
        }

        return new JsonResponse(['message' => 'User registered successfully']);
    }
}
