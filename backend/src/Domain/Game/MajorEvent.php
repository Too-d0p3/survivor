<?php

declare(strict_types=1);

namespace App\Domain\Game;

use App\Domain\Game\Enum\MajorEventType;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: MajorEventRepository::class)]
#[ORM\Index(name: 'idx_major_event_game_tick', columns: ['game_id', 'tick'])]
#[ORM\Index(name: 'idx_major_event_game_type', columns: ['game_id', 'type'])]
final class MajorEvent
{
    private const int MIN_EMOTIONAL_WEIGHT = 1;
    private const int MAX_EMOTIONAL_WEIGHT = 10;
    private const int MAX_SUMMARY_LENGTH = 200;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Game::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Game $game;

    #[ORM\ManyToOne(targetEntity: GameEvent::class)]
    #[ORM\JoinColumn(nullable: false)]
    private GameEvent $sourceEvent;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: MajorEventType::class)]
    private MajorEventType $type;

    #[ORM\Column(type: Types::STRING, length: 200)]
    private string $summary;

    #[ORM\Column(type: Types::INTEGER)]
    private int $emotionalWeight;

    #[ORM\Column(type: Types::INTEGER)]
    private int $day;

    #[ORM\Column(type: Types::INTEGER)]
    private int $hour;

    #[ORM\Column(type: Types::INTEGER)]
    private int $tick;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    /** @var Collection<int, MajorEventParticipant> */
    #[ORM\OneToMany(mappedBy: 'majorEvent', targetEntity: MajorEventParticipant::class, orphanRemoval: true)]
    private Collection $participants;

    public function __construct(
        Game $game,
        GameEvent $sourceEvent,
        MajorEventType $type,
        string $summary,
        int $emotionalWeight,
        int $day,
        int $hour,
        int $tick,
        DateTimeImmutable $now,
    ) {
        $this->id = Uuid::v7();
        $this->game = $game;
        $this->sourceEvent = $sourceEvent;
        $this->type = $type;
        $this->summary = mb_substr($summary, 0, self::MAX_SUMMARY_LENGTH);
        $this->emotionalWeight = max(self::MIN_EMOTIONAL_WEIGHT, min(self::MAX_EMOTIONAL_WEIGHT, $emotionalWeight));
        $this->day = $day;
        $this->hour = $hour;
        $this->tick = $tick;
        $this->createdAt = $now;
        $this->participants = new ArrayCollection();
    }

    public function addParticipant(MajorEventParticipant $participant): void
    {
        if ($this->participants->contains($participant)) {
            return;
        }

        $this->participants->add($participant);
        $participant->setMajorEvent($this);
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getGame(): Game
    {
        return $this->game;
    }

    public function getSourceEvent(): GameEvent
    {
        return $this->sourceEvent;
    }

    public function getType(): MajorEventType
    {
        return $this->type;
    }

    public function getSummary(): string
    {
        return $this->summary;
    }

    public function getEmotionalWeight(): int
    {
        return $this->emotionalWeight;
    }

    public function getDay(): int
    {
        return $this->day;
    }

    public function getHour(): int
    {
        return $this->hour;
    }

    public function getTick(): int
    {
        return $this->tick;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return array<int, MajorEventParticipant>
     */
    public function getParticipants(): array
    {
        return $this->participants->toArray();
    }
}
