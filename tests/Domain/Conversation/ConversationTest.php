<?php declare(strict_types=1);

namespace Tests\Domain\Conversation;

use PHPUnit\Framework\TestCase;
use App\Domain\Conversation\Conversation;
use App\Domain\Conversation\ValueObject\Speaker;
use App\Domain\Conversation\Event\ConversationStoredEvent;
use DateTimeImmutable;

class ConversationTest extends TestCase
{
    public function test_constructor_and_getters(): void
    {
        $botId = 'bot-123';
        $speaker = Speaker::HUMAN;
        $content = 'Hello world';
        $createdAt = new DateTimeImmutable('2025-01-01 10:00:00');
        $id = 'conv-456';

        $conversation = new Conversation($botId, $speaker, $content, $createdAt, $id);

        $this->assertSame($botId, $conversation->getBotId());
        $this->assertSame($speaker, $conversation->getSpeaker());
        $this->assertSame($content, $conversation->getContent());
        $this->assertSame($createdAt, $conversation->getCreatedAt());
        $this->assertSame($id, $conversation->getId());
    }

    public function test_setId_updates_id(): void
    {
        $conversation = new Conversation('bot', Speaker::HUMAN, 'msg');
        $this->assertNull($conversation->getId());

        $conversation->setId('new-id');
        $this->assertSame('new-id', $conversation->getId());
    }

    public function test_constructor_default_values(): void
    {
        $conversation = new Conversation('bot', Speaker::BOT, 'answer');

        $this->assertNull($conversation->getId());
        $this->assertInstanceOf(DateTimeImmutable::class, $conversation->getCreatedAt());
        // createdAt should be recent
        $this->assertTrue((time() - $conversation->getCreatedAt()->getTimestamp()) < 5);
    }

    public function test_domain_events_recording_and_release(): void
    {
        $botId = 'bot-789';
        $speaker = Speaker::HUMAN;
        $content = 'Testing domain events';

        $conversation = new Conversation($botId, $speaker, $content);

        // コンストラクタ呼び出し時にConversationStoredEventが記録されているはず
        $events = $conversation->releaseEvents();
        $this->assertCount(1, $events);

        $event = $events[0];
        $this->assertInstanceOf(ConversationStoredEvent::class, $event);
        $this->assertSame($botId, $event->getBotId());
        $this->assertSame($speaker, $event->getSpeaker());
        $this->assertSame($content, $event->getContent());
        $this->assertInstanceOf(DateTimeImmutable::class, $event->getOccurredAt());
        $this->assertSame('ConversationStoredEvent', $event->getEventName());

        // 放出（リリース）した後は空になっていること
        $this->assertCount(0, $conversation->releaseEvents());
    }
}
