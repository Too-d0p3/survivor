<?php

namespace App\Domain\TraitDef;

use App\Shared\Controller\AbstractApiController;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/trait-def', name: 'trait-def_')]
class TraitDefController extends AbstractApiController
{

    public function __construct(
        private TraitDefRepository $traitDefRepository,
    )
    {
    }

    #[Route('/', name: '', methods: ['GET'])]
    public function traitDefAction(): \Symfony\Component\HttpFoundation\JsonResponse
    {
        return $this->json($this->traitDefRepository->findAll(), 200, ['Content-Type' => 'application/json; charset=utf-8']);
    }
}