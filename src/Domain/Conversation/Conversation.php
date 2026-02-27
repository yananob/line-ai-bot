<?php declare(strict_types=1);

namespace MyApp\Domain\Conversation;

use DateTimeImmutable;

class Conversation
{
    private ?string $id = null;
    private string $botId;
    private string $speaker; // "human" or "bot"
    private string $content;
    private DateTimeImmutable $createdAt;

    public function __construct(
        string $botId,
        string $speaker,
        string $content,
        ?DateTimeImmutable $createdAt = null,
        ?string $id = null
    ) {
        $this->botId = $botId;
        $this->speaker = $speaker;
        $this->content = $content;
        $this->createdAt = $createdAt ?? new DateTimeImmutable();
        $this->id = $id;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getBotId(): string
    {
        return $this->botId;
    }

    public function getSpeaker(): string
    {
        return $this->speaker;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }
}
