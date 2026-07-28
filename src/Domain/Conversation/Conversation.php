<?php declare(strict_types=1);

namespace App\Domain\Conversation;

use DateTimeImmutable;
use App\Domain\Conversation\ValueObject\Speaker;

/**
 * 会話の履歴を表すドメインアグリゲート/エンティティ。
 */
class Conversation
{
    private ?string $id = null;
    private string $botId;
    private Speaker $speaker;
    private string $content;
    private DateTimeImmutable $createdAt;

    public function __construct(
        string $botId,
        Speaker $speaker,
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

    public function getSpeaker(): Speaker
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
