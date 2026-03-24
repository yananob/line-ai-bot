<?php

declare(strict_types=1);

namespace App\Domain\Bot\ValueObject;

/**
 * Represents a message in the chat system.
 */
class Message
{
    private string $content;
    private bool $isSystem;

    public function __construct(string $content, bool $isSystem = false)
    {
        $this->content = $content;
        $this->isSystem = $isSystem;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function isSystem(): bool
    {
        return $this->isSystem;
    }

    public function __toString(): string
    {
        return $this->content;
    }
}
