<?php

declare(strict_types=1);

namespace App\Domain\Player\Trait;

use App\Domain\Player\Player;
use App\Domain\TraitDef\TraitDef;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PlayerTraitRepository::class)]
final class PlayerTrait
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'playerTraits')]
    #[ORM\JoinColumn(nullable: false)]
    private Player $player;

    #[ORM\ManyToOne(inversedBy: 'playerTraits')]
    #[ORM\JoinColumn(nullable: false)]
    private TraitDef $traitDef;

    #[ORM\Column(type: Types::DECIMAL, precision: 3, scale: 2)]
    #[Assert\NotBlank]
    #[Assert\Range(
        min: 0,
        max: 1,
        notInRangeMessage: 'Strength must be between {{ min }} and {{ max }}.',
    )]
    private string $strength;

    public function __construct(Player $player, TraitDef $traitDef, string $strength)
    {
        $this->player = $player;
        $this->traitDef = $traitDef;
        $this->strength = $strength;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPlayer(): Player
    {
        return $this->player;
    }

    public function setPlayer(Player $player): static
    {
        $this->player = $player;
        return $this;
    }

    public function getTraitDef(): TraitDef
    {
        return $this->traitDef;
    }

    public function getStrength(): string
    {
        return $this->strength;
    }
}
