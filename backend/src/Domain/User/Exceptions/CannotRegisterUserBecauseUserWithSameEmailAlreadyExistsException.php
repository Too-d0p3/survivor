<?php

declare(strict_types=1);

namespace App\Domain\User\Exceptions;

use RuntimeException;
use Throwable;

final class CannotRegisterUserBecauseUserWithSameEmailAlreadyExistsException extends RuntimeException
{
    private readonly string $email;

    public function __construct(
        string $email,
        ?Throwable $previous = null,
    ) {
        $this->email = $email;

        parent::__construct(
            sprintf('Cannot register user because user with email `%s` already exists', $email),
            0,
            $previous,
        );
    }

    public function getEmail(): string
    {
        return $this->email;
    }
}
