<?php declare(strict_types=1);

namespace MyApp\Tests\Domain\Conversation;

use PHPUnit\Framework\TestCase;
use MyApp\Domain\Conversation\Conversation;
use DateTimeImmutable;

class ConversationTest extends TestCase
{
    public function test_constructor_and_getters(): void
    {
        $botId = 'bot-123';
        $speaker = 'human';
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
        $conversation = new Conversation('bot', 'human', 'msg');
        $this->assertNull($conversation->getId());

        $conversation->setId('new-id');
        $this->assertSame('new-id', $conversation->getId());
    }

    public function test_constructor_default_values(): void
    {
        $conversation = new Conversation('bot', 'bot', 'answer');

        $this->assertNull($conversation->getId());
        $this->assertInstanceOf(DateTimeImmutable::class, $conversation->getCreatedAt());
        // createdAt should be recent
        $this->assertTrue((time() - $conversation->getCreatedAt()->getTimestamp()) < 5);
    }
}
