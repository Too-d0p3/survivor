<?php

declare(strict_types=1);

namespace App\Dto\Game\Player;

use Symfony\Component\Validator\Constraints as Assert;

class GenerateTraitsInput
{
    #[Assert\NotBlank]
    public string $description;
}
