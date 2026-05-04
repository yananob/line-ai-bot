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
use App\Domain\Bot\ValueObject\Message;
use App\Domain\Bot\Messages;
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
            if ($context === Messages::PROMPT_JUDGE_WEB_SEARCH) {
                return "いいえ";
            }
            return "world";
        });

        $this->convRepoMock->expects($this->exactly(2))->method('save');

        $message = new Message("hello", false);
        $response = $handler->handle($message, $bot, Command::Other);
        $this->assertSame("world", $response->getText());
    }

    /**
     * @dataProvider provideWebSearchJudgmentCases
     */
    public function test_handle_withWebSearch(string $gptJudgment, bool $shouldSearch): void
    {
        $handler = new DefaultChatHandler($this->gptMock, $this->convRepoMock, $this->promptService, $this->webSearchMock);
        $bot = new Bot("test");

        $this->gptMock->method('getAnswer')->willReturnCallback(function($context, $message) use ($gptJudgment) {
            if ($context === Messages::PROMPT_JUDGE_WEB_SEARCH) {
                return $gptJudgment;
            }
            return "Final Answer";
        });

        if ($shouldSearch) {
            $this->webSearchMock->expects($this->once())->method('search')->willReturn("Web info");
        } else {
            $this->webSearchMock->expects($this->never())->method('search');
        }

        $message = new Message("search query", false);
        $response = $handler->handle($message, $bot, Command::Other);
        $this->assertSame("Final Answer", $response->getText());
    }

    public static function provideWebSearchJudgmentCases(): array
    {
        return [
            'Normal Yes' => ["はい", true],
            'Yes with whitespace' => [" はい \n", true],
            'Normal No' => ["いいえ", false],
            'Other response' => ["わからない", false],
        ];
    }

    public function test_handle_withWebSearchToolNull_containsErrorMessageInContext(): void
    {
        $handler = new DefaultChatHandler($this->gptMock, $this->convRepoMock, $this->promptService, null);
        $bot = new Bot("test");

        $this->gptMock->method('getAnswer')->willReturnCallback(function($context, $message) {
            if ($context === Messages::PROMPT_JUDGE_WEB_SEARCH) {
                return "はい";
            }
            // Check if context contains the error message
            if (str_contains($context, "Error: Web search tool is not configured properly or failed to initialize.")) {
                return "Handled Error";
            }
            return "Normal Answer";
        });

        $message = new Message("search please", false);
        $response = $handler->handle($message, $bot, Command::Other);
        $this->assertSame("Handled Error", $response->getText());
    }

    public function test_handle_systemTriggerMessageIsNotStored(): void
    {
        $handler = new DefaultChatHandler($this->gptMock, $this->convRepoMock, $this->promptService, $this->webSearchMock);
        $bot = new Bot("test");

        $this->gptMock->method('getAnswer')->willReturnCallback(function($context, $message) {
            if ($context === Messages::PROMPT_JUDGE_WEB_SEARCH) {
                return "いいえ";
            }
            return "Timer action result";
        });

        // Should NOT call save
        $this->convRepoMock->expects($this->never())->method('save');

        $message = new Message("お昼です", true);
        $response = $handler->handle($message, $bot, Command::Other);
        $this->assertSame("Timer action result", $response->getText());
    }
}
