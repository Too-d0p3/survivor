<?php

namespace App\Domain\Player;

use App\Domain\Game\Game;
use App\Domain\Player\Trait\PlayerTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PlayerRepository::class)]
class Player
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column]
    private ?bool $isUserControlled = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\ManyToOne(inversedBy: 'players')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Game $game = null;

    #[ORM\OneToMany(mappedBy: 'player', targetEntity: PlayerTrait::class, orphanRemoval: true)]
    private Collection $playerTraits;

    public function __construct()
    {
        $this->playerTraits = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function isIsUserControlled(): ?bool
    {
        return $this->isUserControlled;
    }

    public function setIsUserControlled(bool $isUserControlled): static
    {
        $this->isUserControlled = $isUserControlled;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getGame(): ?Game
    {
        return $this->game;
    }

    public function setGame(?Game $game): static
    {
        $this->game = $game;

        return $this;
    }

    /**
     * @return Collection<int, PlayerTrait>
     */
    public function getPlayerTraits(): Collection
    {
        return $this->playerTraits;
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
        if ($this->playerTraits->removeElement($playerTrait)) {
            // set the owning side to null (unless already changed)
            if ($playerTrait->getPlayer() === $this) {
                $playerTrait->setPlayer(null);
            }
        }

        return $this;
    }
} 