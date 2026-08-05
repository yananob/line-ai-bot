<?php declare(strict_types=1);

namespace App\Domain\Conversation\Event;

use App\Domain\Bot\Event\DomainEvent;
use App\Domain\Conversation\ValueObject\Speaker;
use DateTimeImmutable;

/**
 * 会話が記録（保存）された際のドメインイベント。
 */
class ConversationStoredEvent implements DomainEvent
{
    private string $botId;
    private Speaker $speaker;
    private string $content;
    private DateTimeImmutable $occurredAt;

    public function __construct(string $botId, Speaker $speaker, string $content)
    {
        $this->botId = $botId;
        $this->speaker = $speaker;
        $this->content = $content;
        $this->occurredAt = new DateTimeImmutable();
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

    public function getOccurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function getEventName(): string
    {
        return 'ConversationStoredEvent';
    }
}
