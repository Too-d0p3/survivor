<?php

declare(strict_types=1);

namespace App\Domain\Ai\Dto;

final readonly class AiMessage
{
    private string $role;
    private string $content;

    private function __construct(
        string $role,
        string $content,
    ) {
        $this->role = $role;
        $this->content = $content;
    }

    public static function user(string $content): self
    {
        return new self('user', $content);
    }

    public static function model(string $content): self
    {
        return new self('model', $content);
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function getContent(): string
    {
        return $this->content;
    }
}
