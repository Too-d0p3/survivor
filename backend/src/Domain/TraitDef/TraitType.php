<?php

declare(strict_types=1);

namespace App\Domain\TraitDef;

enum TraitType: string
{
    case Social = 'social';
    case Strategic = 'strategic';
    case Emotional = 'emotional';
    case Physical = 'physical';
}
