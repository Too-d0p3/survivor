<?php

namespace App\Domain\User;

use App\Shared\SampleData\SampleData;

class UserSampleData implements SampleData
{

    public function __construct(
        private UserFacade $userFacade
    ) {
    }

    public function create(): void
    {
        $this->userFacade->registerUser('admin@admin.cz', 'admin123');
    }

}