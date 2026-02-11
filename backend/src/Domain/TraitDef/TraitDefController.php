<?php

declare(strict_types=1);

namespace App\Domain\TraitDef;

use App\Shared\Controller\AbstractApiController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/trait-def', name: 'trait-def_')]
class TraitDefController extends AbstractApiController
{
    private TraitDefRepository $traitDefRepository;

    public function __construct(TraitDefRepository $traitDefRepository)
    {
        $this->traitDefRepository = $traitDefRepository;
    }

    #[Route('/', name: '', methods: ['GET'])]
    public function traitDefAction(): JsonResponse
    {
        return $this->json(
            $this->traitDefRepository->findAll(),
            200,
            ['Content-Type' => 'application/json; charset=utf-8'],
        );
    }
}
