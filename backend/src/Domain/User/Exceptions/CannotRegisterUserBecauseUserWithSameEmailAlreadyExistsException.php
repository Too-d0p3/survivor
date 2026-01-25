<?php

namespace App\Domain\User\Exceptions;

use RuntimeException;
use Throwable;

class CannotRegisterUserBecauseUserWithSameEmailAlreadyExistsException extends RuntimeException
{

    public function __construct(
        private string $email,
        ?Throwable $previous = null
    ) {
        parent::__construct(sprintf('Cannot register user because user with email `%s` already exists', $email), $previous);
    }

    public function getEmail(): string
    {
        return $this->email;
    }

}