<?php declare(strict_types=1);

namespace App\Domain\Bot\Event;

use DateTimeImmutable;
use App\Domain\Bot\ValueObject\BotPersonalityConfig;

/**
 * ボットの設定が変更された際のドメインイベント。
 */
class BotConfigChangedEvent implements DomainEvent
{
    private string $botId;
    private string $name;
    private BotPersonalityConfig $personality;
    private DateTimeImmutable $occurredAt;

    public function __construct(string $botId, string $name, BotPersonalityConfig $personality)
    {
        $this->botId = $botId;
        $this->name = $name;
        $this->personality = $personality;
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

    public function getPersonality(): BotPersonalityConfig
    {
        return $this->personality;
    }

    public function getOccurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function getEventName(): string
    {
        return 'BotConfigChangedEvent';
    }
}
