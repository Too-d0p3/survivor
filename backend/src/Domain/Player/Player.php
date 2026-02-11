<?php

declare(strict_types=1);

namespace App\Domain\Player;

use App\Domain\Game\Game;
use App\Domain\Player\Trait\PlayerTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PlayerRepository::class)]
final class Player
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column]
    private bool $isUserControlled;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\ManyToOne(inversedBy: 'players')]
    #[ORM\JoinColumn(nullable: false)]
    private Game $game;

    /** @var Collection<int, PlayerTrait> */
    #[ORM\OneToMany(mappedBy: 'player', targetEntity: PlayerTrait::class, orphanRemoval: true)]
    private Collection $playerTraits;

    public function __construct(string $name, bool $isUserControlled, Game $game)
    {
        $this->name = $name;
        $this->isUserControlled = $isUserControlled;
        $this->game = $game;
        $this->playerTraits = new ArrayCollection();
    }

    public function addPlayerTrait(PlayerTrait $playerTrait): static
    {
        if (!$this->playerTraits->contains($playerTrait)) {
            $this->playerTraits->add($playerTrait);
            $playerTrait->setPlayer($this);
        }

        return $this;
    }

    public function removePlayerTrait(PlayerTrait $playerTrait): static
    {
        $this->playerTraits->removeElement($playerTrait);

        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function isUserControlled(): bool
    {
        return $this->isUserControlled;
    }

    public function getDescription(): ?string
    {
        return $this->description;
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

    public function setGame(Game $game): static
    {
        $this->game = $game;

        return $this;
    }
}
