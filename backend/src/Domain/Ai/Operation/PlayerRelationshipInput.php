<?php

declare(strict_types=1);

namespace App\Domain\Ai\Operation;

final readonly class PlayerRelationshipInput
{
    private string $name;

    private string $description;

    /** @var array<string, string> */
    private array $traitStrengths;

    /**
     * @param array<string, string> $traitStrengths Trait key => formatted float string ("0.72")
     */
    public function __construct(string $name, string $description, array $traitStrengths)
    {
        $this->name = $name;
        $this->description = $description;
        $this->traitStrengths = $traitStrengths;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @return array<string, string>
     */
    public function getTraitStrengths(): array
    {
        return $this->traitStrengths;
    }
}
