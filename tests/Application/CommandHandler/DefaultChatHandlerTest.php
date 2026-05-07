<?php

declare(strict_types=1);

namespace Tests\Application\CommandHandler;

use App\Application\CommandHandler\DefaultChatHandler;
use App\Domain\Bot\ValueObject\Command;
use App\Domain\Bot\Bot;
use App\Domain\Conversation\ConversationRepository;
use App\Domain\Bot\Service\ChatService;
use App\Domain\Bot\ValueObject\Message;
use PHPUnit\Framework\TestCase;

final class DefaultChatHandlerTest extends TestCase
{
    private $chatServiceMock;
    private $convRepoMock;

    protected function setUp(): void
    {
        $this->chatServiceMock = $this->createMock(ChatService::class);
        $this->convRepoMock = $this->createMock(ConversationRepository::class);
    }

    public function test_canHandle(): void
    {
        $handler = new DefaultChatHandler($this->chatServiceMock, $this->convRepoMock);
        $this->assertTrue($handler->canHandle(Command::Other));
        $this->assertFalse($handler->canHandle(Command::ShowHelp));
    }

    public function test_handle(): void
    {
        $handler = new DefaultChatHandler($this->chatServiceMock, $this->convRepoMock);
        $bot = new Bot("test");

        $this->chatServiceMock->expects($this->once())
            ->method('generateAnswer')
            ->willReturn("world");

        $this->convRepoMock->expects($this->exactly(2))->method('save');

        $message = new Message("hello", false);
        $response = $handler->handle($message, $bot, Command::Other);
        $this->assertSame("world", $response->getText());
    }

    public function test_handle_systemTriggerMessageIsNotStored(): void
    {
        $handler = new DefaultChatHandler($this->chatServiceMock, $this->convRepoMock);
        $bot = new Bot("test");

        $this->chatServiceMock->expects($this->once())
            ->method('generateAnswer')
            ->willReturn("Timer action result");

        // Should NOT call save
        $this->convRepoMock->expects($this->never())->method('save');

        $message = new Message("お昼です", true);
        $response = $handler->handle($message, $bot, Command::Other);
        $this->assertSame("Timer action result", $response->getText());
    }
}
