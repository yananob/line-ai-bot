<?php declare(strict_types=1);

namespace App\Domain\Bot\Event;

use DateTimeImmutable;
use App\Domain\Bot\Trigger\Trigger;

/**
 * 新しいトリガーがボットに追加された際のドメインイベント。
 */
class TriggerAddedToBotEvent implements DomainEvent
{
    private string $botId;
    private Trigger $trigger;
    private DateTimeImmutable $occurredAt;

    public function __construct(string $botId, Trigger $trigger)
    {
        $this->botId = $botId;
        $this->trigger = $trigger;
        $this->occurredAt = new DateTimeImmutable();
    }

    public function getBotId(): string
    {
        return $this->botId;
    }

    public function getTrigger(): Trigger
    {
        return $this->trigger;
    }

    public function getOccurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function getEventName(): string
    {
        return 'TriggerAddedToBotEvent';
    }
}
