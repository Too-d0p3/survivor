<?php

declare(strict_types=1);

namespace App\Dto\Game;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateGameInput
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    public string $playerName;

    #[Assert\NotBlank]
    public string $playerDescription;

    /** @var array<string, string> */
    #[Assert\NotNull]
    public array $traitStrengths;

    /**
     * @param array<string, string> $traitStrengths
     */
    public function __construct(
        string $playerName = '',
        string $playerDescription = '',
        array $traitStrengths = [],
    ) {
        $this->playerName = $playerName;
        $this->playerDescription = $playerDescription;
        $this->traitStrengths = $traitStrengths;
    }
}
