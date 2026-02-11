<?php

declare(strict_types=1);

namespace App\Domain\User;

use App\Shared\SampleData\SampleData;

final class UserSampleData implements SampleData
{
    private readonly UserFacade $userFacade;

    public function __construct(UserFacade $userFacade)
    {
        $this->userFacade = $userFacade;
    }

    public function create(): void
    {
        $this->userFacade->registerUser('admin@admin.cz', 'admin123');
    }
}
