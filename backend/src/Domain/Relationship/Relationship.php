<?php

declare(strict_types=1);

namespace App\Domain\Relationship;

use App\Domain\Player\Player;
use App\Domain\Relationship\Exceptions\CannotCreateRelationshipBecauseSourceAndTargetAreTheSamePlayerException;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: RelationshipRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_relationship_source_target', columns: ['source_id', 'target_id'])]
final class Relationship
{
    private const int MIN_SCORE = 0;
    private const int MAX_SCORE = 100;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Player::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Player $source;

    #[ORM\ManyToOne(targetEntity: Player::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Player $target;

    #[ORM\Column(type: Types::INTEGER)]
    private int $trust;

    #[ORM\Column(type: Types::INTEGER)]
    private int $affinity;

    #[ORM\Column(type: Types::INTEGER)]
    private int $respect;

    #[ORM\Column(type: Types::INTEGER)]
    private int $threat;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $updatedAt;

    public function __construct(
        Player $source,
        Player $target,
        int $trust,
        int $affinity,
        int $respect,
        int $threat,
        DateTimeImmutable $createdAt,
    ) {
        if ($source->getId()->equals($target->getId())) {
            throw new CannotCreateRelationshipBecauseSourceAndTargetAreTheSamePlayerException($source);
        }

        $this->id = Uuid::v7();
        $this->source = $source;
        $this->target = $target;
        $this->trust = self::clamp($trust);
        $this->affinity = self::clamp($affinity);
        $this->respect = self::clamp($respect);
        $this->threat = self::clamp($threat);
        $this->createdAt = $createdAt;
        $this->updatedAt = $createdAt;
    }

    public function adjustTrust(int $delta, DateTimeImmutable $now): void
    {
        $this->trust = self::clamp($this->trust + $delta);
        $this->updatedAt = $now;
    }

    public function adjustAffinity(int $delta, DateTimeImmutable $now): void
    {
        $this->affinity = self::clamp($this->affinity + $delta);
        $this->updatedAt = $now;
    }

    public function adjustRespect(int $delta, DateTimeImmutable $now): void
    {
        $this->respect = self::clamp($this->respect + $delta);
        $this->updatedAt = $now;
    }

    public function adjustThreat(int $delta, DateTimeImmutable $now): void
    {
        $this->threat = self::clamp($this->threat + $delta);
        $this->updatedAt = $now;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getSource(): Player
    {
        return $this->source;
    }

    public function getTarget(): Player
    {
        return $this->target;
    }

    public function getTrust(): int
    {
        return $this->trust;
    }

    public function getAffinity(): int
    {
        return $this->affinity;
    }

    public function getRespect(): int
    {
        return $this->respect;
    }

    public function getThreat(): int
    {
        return $this->threat;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private static function clamp(int $value): int
    {
        return max(self::MIN_SCORE, min(self::MAX_SCORE, $value));
    }
}
