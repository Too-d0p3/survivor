<?php

namespace App\Domain\Player\Trait;

use App\Domain\Player\Player;
use App\Domain\TraitDef\TraitDef;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PlayerTraitRepository::class)]
class PlayerTrait
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'playerTraits')] // Vazba na Player
    #[ORM\JoinColumn(nullable: false)]
    private ?Player $player = null;

    #[ORM\ManyToOne(inversedBy: 'playerTraits')] // Vazba na TraitDef
    #[ORM\JoinColumn(nullable: false)]
    private ?TraitDef $traitDef = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 3, scale: 2)] // Např. 0.75
    #[Assert\NotBlank]
    #[Assert\Range(
        min: 0,
        max: 1,
        notInRangeMessage: 'Strength must be between {{ min }} and {{ max }}.',
    )]
    private ?string $strength = null; // DECIMAL se mapuje na string v PHP

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPlayer(): ?Player
    {
        return $this->player;
    }

    public function setPlayer(?Player $player): static
    {
        $this->player = $player;
        return $this;
    }

    public function getTraitDef(): ?TraitDef
    {
        return $this->traitDef;
    }

    public function setTraitDef(?TraitDef $traitDef): static
    {
        $this->traitDef = $traitDef;
        return $this;
    }

    public function getStrength(): ?string
    {
        return $this->strength;
    }

    public function setStrength(string $strength): static
    {
        // Zde by mohla být dodatečná validace, i když Assert\Range to pokryje
        $this->strength = $strength;
        return $this;
    }
} 