<?php

declare(strict_types=1);

namespace Tests\Domain\Bot\Service;

use App\Domain\Bot\Bot;
use App\Domain\Bot\Messages;
use App\Domain\Conversation\ConversationRepository;
use App\Domain\Bot\Service\ChatPromptService;
use App\Domain\Bot\Service\WebSearchInterface;
use App\Domain\Bot\Service\GptInterface;
use App\Domain\Bot\ValueObject\Message;
use App\Domain\Bot\Service\ChatService;
use PHPUnit\Framework\TestCase;

final class ChatServiceTest extends TestCase
{
    private $gptMock;
    private $convRepoMock;
    private $promptService;
    private $webSearchMock;
    private ChatService $chatService;

    protected function setUp(): void
    {
        $this->gptMock = $this->createMock(GptInterface::class);
        $this->convRepoMock = $this->createMock(ConversationRepository::class);
        $this->promptService = new ChatPromptService();
        $this->webSearchMock = $this->createMock(WebSearchInterface::class);
        $this->chatService = new ChatService(
            $this->gptMock,
            $this->convRepoMock,
            $this->promptService,
            $this->webSearchMock
        );
    }

    public function test_generateAnswer(): void
    {
        $bot = new Bot("test");

        $this->gptMock->method('getAnswer')->willReturnCallback(function($context, $message) {
            if ($context === Messages::PROMPT_JUDGE_WEB_SEARCH) {
                return "いいえ";
            }
            return "world";
        });

        $message = new Message("hello", false);
        $answer = $this->chatService->generateAnswer($bot, $message);
        $this->assertSame("world", $answer);
    }

    /**
     * @dataProvider provideWebSearchJudgmentCases
     */
    public function test_generateAnswer_withWebSearch(string $gptJudgment, bool $shouldSearch): void
    {
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
        $answer = $this->chatService->generateAnswer($bot, $message);
        $this->assertSame("Final Answer", $answer);
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

    public function test_generateAnswer_withWebSearchToolNull_containsErrorMessageInContext(): void
    {
        $chatService = new ChatService($this->gptMock, $this->convRepoMock, $this->promptService, null);
        $bot = new Bot("test");

        $this->gptMock->method('getAnswer')->willReturnCallback(function($context, $message) {
            if ($context === Messages::PROMPT_JUDGE_WEB_SEARCH) {
                return "はい";
            }
            if (str_contains($context, "Error: Web search tool is not configured properly or failed to initialize.")) {
                return "Handled Error";
            }
            return "Normal Answer";
        });

        $message = new Message("search please", false);
        $answer = $chatService->generateAnswer($bot, $message);
        $this->assertSame("Handled Error", $answer);
    }
}
