<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Relationship;

use App\Domain\Game\Game;
use App\Domain\Game\GameStatus;
use App\Domain\Player\Player;
use App\Domain\Relationship\Exceptions\CannotCreateRelationshipBecauseSourceAndTargetAreTheSamePlayerException;
use App\Domain\Relationship\Relationship;
use App\Domain\User\User;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class RelationshipTest extends TestCase
{
    public function testConstructorSetsAllPropertiesCorrectly(): void
    {
        $now = new DateTimeImmutable('2026-01-15 12:00:00');
        $source = $this->createPlayer('Alice');
        $target = $this->createPlayer('Bob');

        $relationship = new Relationship($source, $target, 60, 70, 55, 30, $now);

        self::assertSame($source, $relationship->getSource());
        self::assertSame($target, $relationship->getTarget());
        self::assertSame(60, $relationship->getTrust());
        self::assertSame(70, $relationship->getAffinity());
        self::assertSame(55, $relationship->getRespect());
        self::assertSame(30, $relationship->getThreat());
        self::assertSame($now, $relationship->getCreatedAt());
        self::assertSame($now, $relationship->getUpdatedAt());
        self::assertNotEmpty($relationship->getId()->toRfc4122());
    }

    public function testConstructorThrowsExceptionWhenSourceEqualsTarget(): void
    {
        $player = $this->createPlayer('Alice');
        $now = new DateTimeImmutable('2026-01-15 12:00:00');

        $this->expectException(CannotCreateRelationshipBecauseSourceAndTargetAreTheSamePlayerException::class);

        new Relationship($player, $player, 50, 50, 50, 50, $now);
    }

    public function testConstructorClampsValuesAbove100(): void
    {
        $now = new DateTimeImmutable('2026-01-15 12:00:00');
        $source = $this->createPlayer('Alice');
        $target = $this->createPlayer('Bob');

        $relationship = new Relationship($source, $target, 110, 150, 200, 999, $now);

        self::assertSame(100, $relationship->getTrust());
        self::assertSame(100, $relationship->getAffinity());
        self::assertSame(100, $relationship->getRespect());
        self::assertSame(100, $relationship->getThreat());
    }

    public function testConstructorClampsValuesBelow0(): void
    {
        $now = new DateTimeImmutable('2026-01-15 12:00:00');
        $source = $this->createPlayer('Alice');
        $target = $this->createPlayer('Bob');

        $relationship = new Relationship($source, $target, -10, -50, -200, -999, $now);

        self::assertSame(0, $relationship->getTrust());
        self::assertSame(0, $relationship->getAffinity());
        self::assertSame(0, $relationship->getRespect());
        self::assertSame(0, $relationship->getThreat());
    }

    public function testAdjustTrustIncreasesValue(): void
    {
        $now = new DateTimeImmutable('2026-01-15 12:00:00');
        $later = new DateTimeImmutable('2026-01-15 13:00:00');
        $relationship = $this->createRelationship(50, 50, 50, 50, $now);

        $relationship->adjustTrust(10, $later);

        self::assertSame(60, $relationship->getTrust());
        self::assertSame($later, $relationship->getUpdatedAt());
    }

    public function testAdjustTrustClampsAtMax(): void
    {
        $now = new DateTimeImmutable('2026-01-15 12:00:00');
        $relationship = $this->createRelationship(95, 50, 50, 50, $now);

        $relationship->adjustTrust(10, $now);

        self::assertSame(100, $relationship->getTrust());
    }

    public function testAdjustTrustClampsAtMin(): void
    {
        $now = new DateTimeImmutable('2026-01-15 12:00:00');
        $relationship = $this->createRelationship(5, 50, 50, 50, $now);

        $relationship->adjustTrust(-10, $now);

        self::assertSame(0, $relationship->getTrust());
    }

    public function testAdjustAffinityIncreasesValue(): void
    {
        $now = new DateTimeImmutable('2026-01-15 12:00:00');
        $later = new DateTimeImmutable('2026-01-15 13:00:00');
        $relationship = $this->createRelationship(50, 50, 50, 50, $now);

        $relationship->adjustAffinity(15, $later);

        self::assertSame(65, $relationship->getAffinity());
        self::assertSame($later, $relationship->getUpdatedAt());
    }

    public function testAdjustRespectIncreasesValue(): void
    {
        $now = new DateTimeImmutable('2026-01-15 12:00:00');
        $later = new DateTimeImmutable('2026-01-15 13:00:00');
        $relationship = $this->createRelationship(50, 50, 50, 50, $now);

        $relationship->adjustRespect(20, $later);

        self::assertSame(70, $relationship->getRespect());
        self::assertSame($later, $relationship->getUpdatedAt());
    }

    public function testAdjustThreatIncreasesValue(): void
    {
        $now = new DateTimeImmutable('2026-01-15 12:00:00');
        $later = new DateTimeImmutable('2026-01-15 13:00:00');
        $relationship = $this->createRelationship(50, 50, 50, 50, $now);

        $relationship->adjustThreat(25, $later);

        self::assertSame(75, $relationship->getThreat());
        self::assertSame($later, $relationship->getUpdatedAt());
    }

    private function createPlayer(string $name): Player
    {
        $owner = new User('test@example.com');
        $game = new Game($owner, GameStatus::Setup, new DateTimeImmutable());

        return new Player($name, $game);
    }

    private function createRelationship(int $trust, int $affinity, int $respect, int $threat, DateTimeImmutable $now): Relationship
    {
        $source = $this->createPlayer('Alice');
        $target = $this->createPlayer('Bob');

        return new Relationship($source, $target, $trust, $affinity, $respect, $threat, $now);
    }
}
