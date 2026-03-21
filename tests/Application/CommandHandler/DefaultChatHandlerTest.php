<?php

declare(strict_types=1);

namespace Tests\Application\CommandHandler;

use App\Application\CommandHandler\DefaultChatHandler;
use App\Domain\Bot\ValueObject\Command;
use App\Domain\Bot\Bot;
use App\Domain\Conversation\ConversationRepository;
use App\Domain\Bot\Service\ChatPromptService;
use App\Domain\Bot\Service\WebSearchInterface;
use App\Domain\Bot\Service\GptInterface;
use PHPUnit\Framework\TestCase;

final class DefaultChatHandlerTest extends TestCase
{
    private $gptMock;
    private $convRepoMock;
    private $promptService;
    private $webSearchMock;

    protected function setUp(): void
    {
        $this->gptMock = $this->createMock(GptInterface::class);
        $this->convRepoMock = $this->createMock(ConversationRepository::class);
        $this->promptService = new ChatPromptService();
        $this->webSearchMock = $this->createMock(WebSearchInterface::class);
    }

    public function test_canHandle(): void
    {
        $handler = new DefaultChatHandler($this->gptMock, $this->convRepoMock, $this->promptService, $this->webSearchMock);
        $this->assertTrue($handler->canHandle(Command::Other));
        $this->assertFalse($handler->canHandle(Command::ShowHelp));
    }

    public function test_handle(): void
    {
        $handler = new DefaultChatHandler($this->gptMock, $this->convRepoMock, $this->promptService, $this->webSearchMock);
        $bot = new Bot("test");

        // Use willReturnCallback to handle the various calls to getAnswer
        $this->gptMock->method('getAnswer')->willReturnCallback(function($context, $message) {
            if ($context === DefaultChatHandler::PROMPT_JUDGE_WEB_SEARCH) {
                return "いいえ";
            }
            return "world";
        });

        $this->convRepoMock->expects($this->exactly(2))->method('save');

        $response = $handler->handle("hello", $bot, Command::Other);
        $this->assertSame("world", $response->getText());
    }

    public function test_handle_withWebSearch(): void
    {
        $handler = new DefaultChatHandler($this->gptMock, $this->convRepoMock, $this->promptService, $this->webSearchMock);
        $bot = new Bot("test");

        $this->gptMock->method('getAnswer')->willReturnCallback(function($context, $message) {
            if ($context === DefaultChatHandler::PROMPT_JUDGE_WEB_SEARCH) {
                return "はい";
            }
            return "Answer with web results";
        });

        $this->webSearchMock->expects($this->once())->method('search')->willReturn("Web info");

        $response = $handler->handle("search query", $bot, Command::Other);
        $this->assertSame("Answer with web results", $response->getText());
    }
}
