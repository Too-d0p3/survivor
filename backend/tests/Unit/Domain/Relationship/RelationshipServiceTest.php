<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Relationship;

use App\Domain\Ai\Result\InitializeRelationshipsResult;
use App\Domain\Ai\Result\RelationshipValues;
use App\Domain\Game\Game;
use App\Domain\Game\GameStatus;
use App\Domain\Player\Player;
use App\Domain\Relationship\RelationshipService;
use App\Domain\User\User;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class RelationshipServiceTest extends TestCase
{
    public function testInitializeRelationshipsCreatesCorrectNumberOfRelationships(): void
    {
        $service = new RelationshipService();
        $players = $this->createPlayers(6);
        $aiResult = $this->createAiResultForPlayers(6);
        $now = new DateTimeImmutable('2026-01-15 12:00:00');

        $relationships = $service->initializeRelationships($players, $aiResult, $now);

        self::assertCount(30, $relationships);
    }

    public function testInitializeRelationshipsMapsSourcesToCorrectPlayers(): void
    {
        $service = new RelationshipService();
        $players = $this->createPlayers(2);
        $aiResult = new InitializeRelationshipsResult([
            new RelationshipValues(1, 2, 60, 70, 55, 30),
            new RelationshipValues(2, 1, 40, 45, 65, 80),
        ]);
        $now = new DateTimeImmutable('2026-01-15 12:00:00');

        $relationships = $service->initializeRelationships($players, $aiResult, $now);

        self::assertCount(2, $relationships);
        self::assertSame($players[0], $relationships[0]->getSource());
        self::assertSame($players[1], $relationships[0]->getTarget());
        self::assertSame($players[1], $relationships[1]->getSource());
        self::assertSame($players[0], $relationships[1]->getTarget());
    }

    public function testInitializeRelationshipsMapsParsedScores(): void
    {
        $service = new RelationshipService();
        $players = $this->createPlayers(2);
        $aiResult = new InitializeRelationshipsResult([
            new RelationshipValues(1, 2, 60, 70, 55, 30),
            new RelationshipValues(2, 1, 40, 45, 65, 80),
        ]);
        $now = new DateTimeImmutable('2026-01-15 12:00:00');

        $relationships = $service->initializeRelationships($players, $aiResult, $now);

        self::assertSame(60, $relationships[0]->getTrust());
        self::assertSame(70, $relationships[0]->getAffinity());
        self::assertSame(55, $relationships[0]->getRespect());
        self::assertSame(30, $relationships[0]->getThreat());

        self::assertSame(40, $relationships[1]->getTrust());
        self::assertSame(45, $relationships[1]->getAffinity());
        self::assertSame(65, $relationships[1]->getRespect());
        self::assertSame(80, $relationships[1]->getThreat());
    }

    public function testInitializeRelationshipsWithTwoPlayersCreatesTwoRelationships(): void
    {
        $service = new RelationshipService();
        $players = $this->createPlayers(2);
        $aiResult = $this->createAiResultForPlayers(2);
        $now = new DateTimeImmutable('2026-01-15 12:00:00');

        $relationships = $service->initializeRelationships($players, $aiResult, $now);

        self::assertCount(2, $relationships);
    }

    public function testInitializeRelationshipsSetsCreatedAt(): void
    {
        $service = new RelationshipService();
        $players = $this->createPlayers(2);
        $aiResult = $this->createAiResultForPlayers(2);
        $now = new DateTimeImmutable('2026-01-15 12:00:00');

        $relationships = $service->initializeRelationships($players, $aiResult, $now);

        foreach ($relationships as $relationship) {
            self::assertSame($now, $relationship->getCreatedAt());
        }
    }

    /**
     * @return array<int, Player>
     */
    private function createPlayers(int $count): array
    {
        $owner = new User('test@example.com');
        $game = new Game($owner, GameStatus::Setup, new DateTimeImmutable());
        $names = ['Alice', 'Bob', 'Charlie', 'Dana', 'Emil', 'Fiona'];

        $players = [];
        for ($i = 0; $i < $count; $i++) {
            $players[] = new Player($names[$i], $game);
        }

        return $players;
    }

    private function createAiResultForPlayers(int $playerCount): InitializeRelationshipsResult
    {
        $relationships = [];

        for ($source = 1; $source <= $playerCount; $source++) {
            for ($target = 1; $target <= $playerCount; $target++) {
                if ($source === $target) {
                    continue;
                }

                $relationships[] = new RelationshipValues($source, $target, 50, 50, 50, 50);
            }
        }

        return new InitializeRelationshipsResult($relationships);
    }
}
