<?php

declare(strict_types=1);

namespace App\Domain\Game;

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

    #[ORM\Column]
    private bool $isSandbox;

    /** @var Collection<int, Player> */
    #[ORM\OneToMany(mappedBy: 'game', targetEntity: Player::class, orphanRemoval: true)]
    private Collection $players;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $owner;

    public function __construct(User $owner, bool $isSandbox, DateTimeImmutable $createdAt)
    {
        $this->id = Uuid::v7();
        $this->owner = $owner;
        $this->isSandbox = $isSandbox;
        $this->createdAt = $createdAt;
        $this->players = new ArrayCollection();
    }

    public function addPlayer(Player $player): static
    {
        if (!$this->players->contains($player)) {
            $this->players->add($player);
            $player->setGame($this);
        }

        return $this;
    }

    public function removePlayer(Player $player): static
    {
        $this->players->removeElement($player);

        return $this;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isSandbox(): bool
    {
        return $this->isSandbox;
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

    public function setOwner(User $owner): static
    {
        $this->owner = $owner;
        return $this;
    }
}
