<?php

declare(strict_types=1);

namespace App\Domain\Player;

use App\Domain\Game\Game;
use App\Domain\Player\Trait\PlayerTrait;
use App\Domain\User\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: PlayerRepository::class)]
final class Player
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\ManyToOne(inversedBy: 'players')]
    #[ORM\JoinColumn(nullable: false)]
    private Game $game;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $user;

    /** @var Collection<int, PlayerTrait> */
    #[ORM\OneToMany(mappedBy: 'player', targetEntity: PlayerTrait::class, orphanRemoval: true)]
    private Collection $playerTraits;

    public function __construct(string $name, Game $game, ?User $user = null)
    {
        $this->id = Uuid::v7();
        $this->name = $name;
        $this->game = $game;
        $this->user = $user;
        $this->playerTraits = new ArrayCollection();
    }

    public function addPlayerTrait(PlayerTrait $playerTrait): void
    {
        if ($this->playerTraits->contains($playerTrait)) {
            return;
        }

        $this->playerTraits->add($playerTrait);
        $playerTrait->setPlayer($this);
    }

    public function removePlayerTrait(PlayerTrait $playerTrait): void
    {
        $this->playerTraits->removeElement($playerTrait);
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function isHuman(): bool
    {
        return $this->user !== null;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function getGame(): Game
    {
        return $this->game;
    }

    /**
     * @return array<int, PlayerTrait>
     */
    public function getPlayerTraits(): array
    {
        return $this->playerTraits->toArray();
    }

    public function setGame(Game $game): void
    {
        $this->game = $game;
    }
}
