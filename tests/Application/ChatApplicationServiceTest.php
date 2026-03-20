<?php

declare(strict_types=1);

namespace Tests\Application;

use App\Application\ChatApplicationService;
use App\Application\BotResponse;
use App\Domain\Bot\Service\CommandAndTriggerService;
use App\Domain\Bot\ValueObject\Command;
use App\Domain\Bot\Bot;
use App\Application\CommandHandler\CommandHandlerInterface;
use App\Application\CommandHandler\PostbackHandlerInterface;
use PHPUnit\Framework\TestCase;

final class ChatApplicationServiceTest extends TestCase
{
    private ChatApplicationService $chatService;
    private $bot;
    private $commandAndTriggerServiceMock;
    private $messageHandlerMock;
    private $postbackHandlerMock;

    const TARGET_ID = "TARGET_ID";

    protected function setUp(): void
    {
        $this->bot = new Bot(self::TARGET_ID);
        $this->commandAndTriggerServiceMock = $this->createMock(CommandAndTriggerService::class);
        $this->messageHandlerMock = $this->createMock(CommandHandlerInterface::class);
        $this->postbackHandlerMock = $this->createMock(PostbackHandlerInterface::class);

        $this->chatService = new ChatApplicationService(
            $this->bot,
            $this->commandAndTriggerServiceMock,
            [$this->messageHandlerMock],
            [$this->postbackHandlerMock]
        );
    }

    public function test_handleMessage_delegates_to_handler(): void
    {
        $this->commandAndTriggerServiceMock->method('judgeCommand')->willReturn(Command::Other);
        $this->messageHandlerMock->method('canHandle')->with(Command::Other)->willReturn(true);
        $this->messageHandlerMock->expects($this->once())
            ->method('handle')
            ->with("hello", $this->bot, Command::Other)
            ->willReturn(new BotResponse("hi"));

        $response = $this->chatService->handleMessage("hello");
        $this->assertSame("hi", $response->getText());
    }

    public function test_handleMessage_throws_exception_if_no_handler_found(): void
    {
        $this->commandAndTriggerServiceMock->method('judgeCommand')->willReturn(Command::Other);
        $this->messageHandlerMock->method('canHandle')->willReturn(false);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("No handler found for command: 9");

        $this->chatService->handleMessage("hello");
    }

    public function test_handlePostback_delegates_to_handler(): void
    {
        $data = "command=test_cmd&id=123";
        $this->postbackHandlerMock->method('canHandle')->with("test_cmd")->willReturn(true);
        $this->postbackHandlerMock->expects($this->once())
            ->method('handle')
            ->with(["command" => "test_cmd", "id" => "123"], $this->bot)
            ->willReturn(new BotResponse("postback response"));

        $response = $this->chatService->handlePostback($data);
        $this->assertSame("postback response", $response->getText());
    }

    public function test_handlePostback_throws_exception_if_unsupported(): void
    {
        $data = "command=unknown";
        $this->postbackHandlerMock->method('canHandle')->willReturn(false);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Unsupported postback command: unknown");

        $this->chatService->handlePostback($data);
    }

    public function test_getLineTarget_returns_test_in_testing_env(): void
    {
        // CFUtils::isTestingEnv() is mocked by environment variable or usually returns true in tests
        $this->assertSame('test', $this->chatService->getLineTarget());
    }
}
