<?php

declare(strict_types=1);

namespace App\Domain\Game;

use App\Domain\Game\Exceptions\CannotAdvanceTickBecauseGameIsNotInProgressException;
use App\Domain\Game\Exceptions\CannotStartGameBecauseGameIsNotInSetupException;
use App\Domain\Player\Player;
use App\Domain\User\User;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: GameRepository::class)]
final class Game
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: GameStatus::class)]
    private GameStatus $status;

    /** @var Collection<int, Player> */
    #[ORM\OneToMany(mappedBy: 'game', targetEntity: Player::class, orphanRemoval: true)]
    private Collection $players;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $owner;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $currentDay = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $currentHour = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $currentTick = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $startedAt = null;

    public function __construct(User $owner, GameStatus $status, DateTimeImmutable $createdAt)
    {
        $this->id = Uuid::v7();
        $this->owner = $owner;
        $this->status = $status;
        $this->createdAt = $createdAt;
        $this->players = new ArrayCollection();
    }

    public function start(DateTimeImmutable $startedAt): void
    {
        if ($this->status !== GameStatus::Setup) {
            throw new CannotStartGameBecauseGameIsNotInSetupException($this);
        }

        $this->status = GameStatus::InProgress;
        $this->currentDay = 1;
        $this->currentHour = 6;
        $this->currentTick = 0;
        $this->startedAt = $startedAt;
    }

    public function advanceTick(): void
    {
        if ($this->status !== GameStatus::InProgress) {
            throw new CannotAdvanceTickBecauseGameIsNotInProgressException($this);
        }

        assert($this->currentTick !== null);
        assert($this->currentHour !== null);

        $this->currentTick++;
        $this->currentHour += 2;
    }

    public function sleepToNextDay(): void
    {
        if ($this->status !== GameStatus::InProgress) {
            throw new CannotAdvanceTickBecauseGameIsNotInProgressException($this);
        }

        assert($this->currentDay !== null);

        $this->currentDay++;
        $this->currentHour = 6;
    }

    public function addPlayer(Player $player): void
    {
        if ($this->players->contains($player)) {
            return;
        }

        $this->players->add($player);
        $player->setGame($this);
    }

    public function removePlayer(Player $player): void
    {
        $this->players->removeElement($player);
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getStatus(): GameStatus
    {
        return $this->status;
    }

    /**
     * @return array<int, Player>
     */
    public function getPlayers(): array
    {
        return $this->players->toArray();
    }

    public function getOwner(): User
    {
        return $this->owner;
    }

    public function getCurrentDay(): ?int
    {
        return $this->currentDay;
    }

    public function getCurrentHour(): ?int
    {
        return $this->currentHour;
    }

    public function getCurrentTick(): ?int
    {
        return $this->currentTick;
    }

    public function getStartedAt(): ?DateTimeImmutable
    {
        return $this->startedAt;
    }
}
