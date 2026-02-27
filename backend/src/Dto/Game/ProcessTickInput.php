<?php

declare(strict_types=1);

namespace App\Dto\Game;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ProcessTickInput
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 500)]
    public string $actionText;

    public function __construct(
        string $actionText = '',
    ) {
        $this->actionText = $actionText;
    }
}
