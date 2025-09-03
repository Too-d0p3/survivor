<?php

namespace App\Domain\TraitDef;

use App\Shared\Controller\AbstractApiController;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/trait-def', name: 'trait-def_')]
class TraitDefController extends AbstractApiController
{
    #[Route('/', name: '', methods: ['GET'])]
    public function traitDefAction(): \Symfony\Component\HttpFoundation\JsonResponse
    {
        return $this->json(['asd'], 200, ['Content-Type' => 'application/json; charset=utf-8']);
    }
}