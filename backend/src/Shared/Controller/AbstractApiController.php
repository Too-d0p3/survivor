<?php

declare(strict_types=1);

namespace App\Shared\Controller;

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
        ValidatorInterface $validator,
    ): ValidationResult {
        $data = $request->getContent();

        $dto = $serializer->deserialize($data, $dtoClass, 'json');
        assert(is_object($dto));
        $errors = $validator->validate($dto);

        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = sprintf('%s: %s', $error->getPropertyPath(), $error->getMessage());
            }

            return new ValidationResult(null, $errorMessages);
        }

        return new ValidationResult($dto, []);
    }
}
