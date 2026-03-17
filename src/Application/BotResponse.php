<?php

declare(strict_types=1);

namespace MyApp\Application;

class BotResponse
{
    private string $text;
    private ?array $quickReply;

    public function __construct(string $text, ?array $quickReply = null)
    {
        $this->text = $text;
        $this->quickReply = $quickReply;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function getQuickReply(): ?array
    {
        return $this->quickReply;
    }
}
