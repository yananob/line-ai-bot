<?php declare(strict_types=1);

namespace Tests\Domain\Bot\Event;

use PHPUnit\Framework\TestCase;
use App\Domain\Bot\Event\DomainEventPublisher;
use App\Domain\Bot\Event\BotCreatedEvent;
use App\Domain\Bot\Event\BotConfigChangedEvent;
use App\Domain\Bot\ValueObject\BotPersonalityConfig;
use App\Domain\Bot\ValueObject\StringList;

class DomainEventPublisherTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DomainEventPublisher::getInstance()->clear();
    }

    protected function tearDown(): void
    {
        DomainEventPublisher::getInstance()->clear();
        parent::tearDown();
    }

    public function test_subscribe_and_publish_specific_event(): void
    {
        $publisher = DomainEventPublisher::getInstance();

        $receivedEvent = null;
        $publisher->subscribe(BotCreatedEvent::class, function ($event) use (&$receivedEvent) {
            $receivedEvent = $event;
        });

        $event = new BotCreatedEvent("bot-123", "Test Bot");
        $publisher->publish($event);

        $this->assertSame($event, $receivedEvent);
    }

    public function test_subscribe_all_events_using_wildcard(): void
    {
        $publisher = DomainEventPublisher::getInstance();

        $receivedEvents = [];
        $publisher->subscribe('*', function ($event) use (&$receivedEvents) {
            $receivedEvents[] = $event;
        });

        $event1 = new BotCreatedEvent("bot-123", "Test Bot");
        $event2 = new BotConfigChangedEvent("bot-123", "Test Bot 2", new BotPersonalityConfig(new StringList([]), new StringList([])));

        $publisher->publish($event1);
        $publisher->publish($event2);

        $this->assertCount(2, $receivedEvents);
        $this->assertSame($event1, $receivedEvents[0]);
        $this->assertSame($event2, $receivedEvents[1]);
    }
}
