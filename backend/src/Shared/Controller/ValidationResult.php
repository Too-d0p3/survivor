<?php

declare(strict_types=1);

namespace App\Shared\Controller;

final readonly class ValidationResult
{
    /** @var array<string> */
    public array $errors;

    public ?object $dto;

    /**
     * @param array<string> $errors
     */
    public function __construct(?object $dto, array $errors)
    {
        $this->dto = $dto;
        $this->errors = $errors;
    }

    public function isValid(): bool
    {
        return $this->dto !== null && $this->errors === [];
    }
}
