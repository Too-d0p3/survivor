<?php

declare(strict_types=1);

namespace App\Domain\Ai\Operation;

final readonly class SimulationPlayerInput
{
    private int $index;

    private string $name;

    private string $description;

    /** @var array<string, string> */
    private array $traitStrengths;

    private bool $isHuman;

    /**
     * @param array<string, string> $traitStrengths Trait key => formatted float string ("0.72")
     */
    public function __construct(int $index, string $name, string $description, array $traitStrengths, bool $isHuman)
    {
        $this->index = $index;
        $this->name = $name;
        $this->description = $description;
        $this->traitStrengths = $traitStrengths;
        $this->isHuman = $isHuman;
    }

    public function getIndex(): int
    {
        return $this->index;
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

    public function isHuman(): bool
    {
        return $this->isHuman;
    }
}
