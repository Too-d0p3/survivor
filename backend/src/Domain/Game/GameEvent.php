<?php

declare(strict_types=1);

namespace App\Domain\Game;

use App\Domain\Game\Enum\GameEventType;
use App\Domain\Player\Player;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: GameEventRepository::class)]
#[ORM\Index(name: 'idx_game_event_game_tick', columns: ['game_id', 'tick'])]
final class GameEvent
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Game::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Game $game;

    #[ORM\Column(type: Types::STRING, length: 30, enumType: GameEventType::class)]
    private GameEventType $type;

    #[ORM\Column(type: Types::INTEGER)]
    private int $day;

    #[ORM\Column(type: Types::INTEGER)]
    private int $hour;

    #[ORM\Column(type: Types::INTEGER)]
    private int $tick;

    #[ORM\ManyToOne(targetEntity: Player::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Player $player;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $narrative;

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $metadata;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    /**
     * @param array<string, mixed>|null $metadata
     */
    public function __construct(
        Game $game,
        GameEventType $type,
        int $day,
        int $hour,
        int $tick,
        DateTimeImmutable $createdAt,
        ?Player $player = null,
        ?string $narrative = null,
        ?array $metadata = null,
    ) {
        $this->id = Uuid::v7();
        $this->game = $game;
        $this->type = $type;
        $this->day = $day;
        $this->hour = $hour;
        $this->tick = $tick;
        $this->createdAt = $createdAt;
        $this->player = $player;
        $this->narrative = $narrative;
        $this->metadata = $metadata;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getGame(): Game
    {
        return $this->game;
    }

    public function getType(): GameEventType
    {
        return $this->type;
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

    public function getPlayer(): ?Player
    {
        return $this->player;
    }

    public function getNarrative(): ?string
    {
        return $this->narrative;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
