<?php

declare(strict_types=1);

namespace App\Domain\Game;

use App\Domain\Game\Result\CreateGameResult;
use App\Domain\Player\PlayerService;
use App\Domain\TraitDef\TraitDefRepository;
use App\Domain\User\User;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

final class GameFacade
{
    private readonly EntityManagerInterface $entityManager;

    private readonly GameService $gameService;

    private readonly PlayerService $playerService;

    private readonly TraitDefRepository $traitDefRepository;

    public function __construct(
        EntityManagerInterface $entityManager,
        GameService $gameService,
        PlayerService $playerService,
        TraitDefRepository $traitDefRepository,
    ) {
        $this->entityManager = $entityManager;
        $this->gameService = $gameService;
        $this->playerService = $playerService;
        $this->traitDefRepository = $traitDefRepository;
    }

    /**
     * @param array<string, string> $humanTraitStrengths
     */
    public function createGame(
        User $owner,
        string $humanPlayerName,
        string $humanPlayerDescription,
        array $humanTraitStrengths,
    ): CreateGameResult {
        $now = new DateTimeImmutable();
        $traitDefs = array_values($this->traitDefRepository->findAll());

        $aiTraitStrengths = [];
        for ($i = 0; $i < 5; $i++) {
            $aiTraitStrengths[] = $this->playerService->generateRandomTraitStrengths($traitDefs);
        }

        $batchServiceResult = $this->playerService->generateBatchPlayerTraitsSummaryDescriptions($aiTraitStrengths, $now);

        foreach ($batchServiceResult->getLogs() as $log) {
            $this->entityManager->persist($log);
        }

        $this->entityManager->flush();

        if (!$batchServiceResult->isSuccess()) {
            throw $batchServiceResult->getError();
        }

        $aiDescriptions = $batchServiceResult->getResult()->getSummaries();

        $result = $this->gameService->createGame(
            $owner,
            $humanPlayerName,
            $humanPlayerDescription,
            $humanTraitStrengths,
            $traitDefs,
            $aiTraitStrengths,
            $aiDescriptions,
            $now,
        );

        $game = $result->game;

        $this->entityManager->persist($game);

        foreach ($game->getPlayers() as $player) {
            $this->entityManager->persist($player);

            foreach ($player->getPlayerTraits() as $playerTrait) {
                $this->entityManager->persist($playerTrait);
            }
        }

        $this->entityManager->flush();

        return $result;
    }
}
