<?php

declare(strict_types=1);

namespace App\Domain\Ai\Dto;

final readonly class AiResponseSchema
{
    private string $type;
    /** @var array<string, array<string, mixed>> */
    private array $properties;
    /** @var array<int, string> */
    private array $required;
    private ?string $description;

    /**
     * @param array<string, array<string, mixed>> $properties
     * @param array<int, string> $required
     */
    public function __construct(
        string $type,
        array $properties,
        array $required,
        ?string $description = null,
    ) {
        $this->type = $type;
        $this->properties = $properties;
        $this->required = $required;
        $this->description = $description;
    }

    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getProperties(): array
    {
        return $this->properties;
    }

    /**
     * @return array<int, string>
     */
    public function getRequired(): array
    {
        return $this->required;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = [
            'type' => $this->type,
            'properties' => $this->properties,
            'required' => $this->required,
        ];

        if ($this->description !== null) {
            $result['description'] = $this->description;
        }

        return $result;
    }
}
