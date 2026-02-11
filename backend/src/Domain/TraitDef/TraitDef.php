<?php

declare(strict_types=1);

namespace App\Domain\TraitDef;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TraitDefRepository::class)]
final class TraitDef
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100, unique: true)]
    #[Assert\NotBlank]
    private string $key;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private string $label;

    #[ORM\Column(type: Types::TEXT, nullable: false)]
    private string $description;

    #[ORM\Column(type: Types::STRING, length: 50, enumType: TraitType::class)]
    private TraitType $type;

    public function __construct(
        string $key,
        string $label,
        string $description,
        TraitType $type,
    ) {
        $this->key = $key;
        $this->label = $label;
        $this->description = $description;
        $this->type = $type;
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

    public function getType(): TraitType
    {
        return $this->type;
    }
}
