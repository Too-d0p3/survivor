<?php
namespace App\Dto\Game\Player;

use Symfony\Component\Validator\Constraints as Assert;

class GenerateTraitsInput
{
    #[Assert\NotBlank]
    public string $description;
}
