<?php

declare(strict_types=1);

namespace App\Domain\Player\Trait;

use App\Domain\Player\Player;
use App\Domain\TraitDef\TraitDef;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PlayerTraitRepository::class)]
final class PlayerTrait
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

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
        $this->id = Uuid::v7();
        $this->player = $player;
        $this->traitDef = $traitDef;
        $this->strength = $strength;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getPlayer(): Player
    {
        return $this->player;
    }

    public function setPlayer(Player $player): void
    {
        $this->player = $player;
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
