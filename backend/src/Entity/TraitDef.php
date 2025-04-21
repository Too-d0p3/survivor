<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\TraitDefRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TraitDefRepository::class)]
#[ApiResource] // Pokud chcete API endpointy
class TraitDef
{
    // Konstanta pro typy
    public const TYPE_SOCIAL = 'social';
    public const TYPE_STRATEGIC = 'strategic';
    public const TYPE_EMOTIONAL = 'emotional';
    public const TYPE_PHYSICAL = 'physical';

    public const ALLOWED_TYPES = [
        self::TYPE_SOCIAL,
        self::TYPE_STRATEGIC,
        self::TYPE_EMOTIONAL,
        self::TYPE_PHYSICAL,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100, unique: true)] // Klíč by měl být unikátní
    #[Assert\NotBlank]
    private ?string $key = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $label = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 50)]
    #[Assert\Choice(choices: self::ALLOWED_TYPES, message: 'Invalid trait type.')]
    #[Assert\NotBlank]
    private ?string $type = null;

    #[ORM\OneToMany(mappedBy: 'traitDef', targetEntity: PlayerTrait::class, orphanRemoval: true)]
    private Collection $playerTraits;

    public function __construct()
    {
        $this->playerTraits = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getKey(): ?string
    {
        return $this->key;
    }

    public function setKey(string $key): static
    {
        $this->key = $key;
        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = $label;
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

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        if (!in_array($type, self::ALLOWED_TYPES)) {
            throw new \InvalidArgumentException("Invalid trait type");
        }
        $this->type = $type;
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
            $playerTrait->setTraitDef($this);
        }
        return $this;
    }

    public function removePlayerTrait(PlayerTrait $playerTrait): static
    {
        if ($this->playerTraits->removeElement($playerTrait)) {
            // set the owning side to null (unless already changed)
            if ($playerTrait->getTraitDef() === $this) {
                $playerTrait->setTraitDef(null);
            }
        }
        return $this;
    }
} 