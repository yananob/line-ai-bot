<?php declare(strict_types=1);

namespace App\Domain\Bot\Event;

use DateTimeImmutable;

/**
 * ボットが新規作成された際のドメインイベント。
 */
class BotCreatedEvent implements DomainEvent
{
    private string $botId;
    private string $name;
    private DateTimeImmutable $occurredAt;

    public function __construct(string $botId, string $name)
    {
        $this->botId = $botId;
        $this->name = $name;
        $this->occurredAt = new DateTimeImmutable();
    }

    public function getBotId(): string
    {
        return $this->botId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getOccurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function getEventName(): string
    {
        return 'BotCreatedEvent';
    }
}
