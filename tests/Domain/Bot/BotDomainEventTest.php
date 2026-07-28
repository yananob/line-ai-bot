<?php declare(strict_types=1);

namespace Tests\Domain\Bot;

use PHPUnit\Framework\TestCase;
use App\Domain\Bot\Bot;
use App\Domain\Bot\ValueObject\BotPersonalityConfig;
use App\Domain\Bot\ValueObject\StringList;
use App\Domain\Bot\Trigger\TimerTrigger;
use App\Domain\Bot\Event\BotConfigChangedEvent;
use App\Domain\Bot\Event\TriggerAddedToBotEvent;
use App\Domain\Bot\Event\TriggerRemovedFromBotEvent;

/**
 * Botアグリゲートにおけるドメインイベント発生テスト。
 */
class BotDomainEventTest extends TestCase
{
    public function test_bot_records_and_releases_config_changed_events(): void
    {
        $bot = new Bot("test-bot", "Initial Name");

        // 初期状態ではイベントは空であること
        $this->assertEmpty($bot->releaseEvents());

        // 1. setName() で BotConfigChangedEvent が記録されること
        $bot->setName("New Name");
        $events = $bot->releaseEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(BotConfigChangedEvent::class, $events[0]);
        $this->assertSame("test-bot", $events[0]->getBotId());
        $this->assertSame("New Name", $events[0]->getName());
        $this->assertSame("BotConfigChangedEvent", $events[0]->getEventName());
        $this->assertInstanceOf(\DateTimeImmutable::class, $events[0]->getOccurredAt());

        // 放出（リリース）した後はイベントキューが空になること
        $this->assertEmpty($bot->releaseEvents());

        // 2. setPersonality() で BotConfigChangedEvent が記録されること
        $personality = new BotPersonalityConfig(new StringList(["gentle"]), new StringList(["talkative"]));
        $bot->setPersonality($personality);
        $events = $bot->releaseEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(BotConfigChangedEvent::class, $events[0]);
        $this->assertSame($personality, $events[0]->getPersonality());

        // 3. setConfigRequests() で BotConfigChangedEvent が記録されること
        $bot->setConfigRequests(new StringList(["daily report"]));
        $events = $bot->releaseEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(BotConfigChangedEvent::class, $events[0]);

        // 4. setLineTarget() で BotConfigChangedEvent が記録されること
        $bot->setLineTarget("user-123");
        $events = $bot->releaseEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(BotConfigChangedEvent::class, $events[0]);
    }

    public function test_bot_records_trigger_added_and_removed_events(): void
    {
        $bot = new Bot("test-bot");

        $trigger = new TimerTrigger("today", "12:00", "Send Reminder");

        // 1. addTrigger() で TriggerAddedToBotEvent が記録されること
        $triggerId = $bot->addTrigger($trigger);
        $events = $bot->releaseEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(TriggerAddedToBotEvent::class, $events[0]);
        $this->assertSame("test-bot", $events[0]->getBotId());
        $this->assertSame($trigger, $events[0]->getTrigger());
        $this->assertSame("TriggerAddedToBotEvent", $events[0]->getEventName());

        // 2. deleteTriggerById() で TriggerRemovedFromBotEvent が記録されること
        $bot->deleteTriggerById($triggerId);
        $events = $bot->releaseEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(TriggerRemovedFromBotEvent::class, $events[0]);
        $this->assertSame("test-bot", $events[0]->getBotId());
        $this->assertSame($triggerId, $events[0]->getTriggerId());
        $this->assertSame("TriggerRemovedFromBotEvent", $events[0]->getEventName());
    }
}
