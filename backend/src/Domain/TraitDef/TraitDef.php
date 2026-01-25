<?php

namespace App\Domain\TraitDef;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TraitDefRepository::class)]
class TraitDef
{
    // Konstanta pro typy
    public const TYPE_SOCIAL = 'social';
    public const TYPE_STRATEGIC = 'strategic';
    public const TYPE_EMOTIONAL = 'emotional';
    public const TYPE_PHYSICAL = 'physical';

    //TODO enum
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
    private string $key;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private string $label;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private string $description;

    #[ORM\Column(length: 50)]
    #[Assert\Choice(choices: self::ALLOWED_TYPES, message: 'Invalid trait type.')]
    #[Assert\NotBlank]
    private string $type;

    public function __construct(
        string $key,
        string $label,
        string $description,
        string $type,
    )
    {
        $this->key = $key;
        $this->label = $label;
        $this->description = $description;
        $this->setType($type);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getType(): string
    {
        return $this->type;
    }

    private function setType(string $type): static
    {
        if (!in_array($type, self::ALLOWED_TYPES)) {
            throw new \InvalidArgumentException("Invalid trait type");
        }
        $this->type = $type;
        return $this;
    }

} 