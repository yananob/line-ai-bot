<?php

declare(strict_types=1);

namespace App\Domain\Bot\ValueObject;

/**
 * Represents a message in the chat system.
 */
class Message
{
    private MessageContent $content;
    private bool $isSystem;

    public function __construct(string|MessageContent $content, bool $isSystem = false)
    {
        $this->content = $content instanceof MessageContent ? $content : new MessageContent($content);
        $this->isSystem = $isSystem;
    }

    public function getContent(): string
    {
        return $this->content->getValue();
    }

    public function getMessageContent(): MessageContent
    {
        return $this->content;
    }

    public function isSystem(): bool
    {
        return $this->isSystem;
    }

    public function __toString(): string
    {
        return (string)$this->content;
    }
}
