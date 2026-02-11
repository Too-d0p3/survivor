<?php

declare(strict_types=1);

namespace App\Dto\Game\Player;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class GenerateTraitsInput
{
    #[Assert\NotBlank]
    public string $description;

    public function __construct(
        string $description = '',
    ) {
        $this->description = $description;
    }
}
