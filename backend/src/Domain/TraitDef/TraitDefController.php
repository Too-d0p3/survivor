<?php

declare(strict_types=1);

namespace App\Domain\TraitDef;

use App\Shared\Controller\AbstractApiController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/trait-def', name: 'trait-def_')]
final class TraitDefController extends AbstractApiController
{
    private readonly TraitDefFacade $traitDefFacade;

    public function __construct(TraitDefFacade $traitDefFacade)
    {
        $this->traitDefFacade = $traitDefFacade;
    }

    #[Route('/', name: '', methods: ['GET'])]
    public function traitDefAction(): JsonResponse
    {
        return $this->json(
            $this->traitDefFacade->getAll(),
            200,
            ['Content-Type' => 'application/json; charset=utf-8'],
        );
    }
}
