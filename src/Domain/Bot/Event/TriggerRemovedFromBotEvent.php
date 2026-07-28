<?php declare(strict_types=1);

namespace App\Domain\Bot\Event;

use DateTimeImmutable;

/**
 * トリガーがボットから削除された際のドメインイベント。
 */
class TriggerRemovedFromBotEvent implements DomainEvent
{
    private string $botId;
    private string $triggerId;
    private DateTimeImmutable $occurredAt;

    public function __construct(string $botId, string $triggerId)
    {
        $this->botId = $botId;
        $this->triggerId = $triggerId;
        $this->occurredAt = new DateTimeImmutable();
    }

    public function getBotId(): string
    {
        return $this->botId;
    }

    public function getTriggerId(): string
    {
        return $this->triggerId;
    }

    public function getOccurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function getEventName(): string
    {
        return 'TriggerRemovedFromBotEvent';
    }
}
