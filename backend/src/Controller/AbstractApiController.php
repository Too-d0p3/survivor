<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

abstract class AbstractApiController extends AbstractController
{
    public function getValidatedDto(
        Request $request,
        string $dtoClass,
        SerializerInterface $serializer,
        ValidatorInterface $validator
    ): array {
        $data = $request->getContent();

        $dto = $serializer->deserialize($data, $dtoClass, 'json');
        $errors = $validator->validate($dto);

        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getPropertyPath() . ': ' . $error->getMessage();
            }

            return [null, $errorMessages];
        }

        return [$dto, []];
    }
}