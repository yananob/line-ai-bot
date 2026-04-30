<?php

declare(strict_types=1);

namespace Tests\Application;

use App\Application\ChatApplicationService;
use App\Application\BotResponse;
use App\Domain\Exception\HandlerNotFoundException;
use App\Domain\Bot\Service\CommandAndTriggerService;
use App\Domain\Bot\ValueObject\Command;
use App\Domain\Bot\Bot;
use App\Domain\Bot\Trigger\TimerTrigger;
use App\Domain\Bot\ValueObject\Message;
use App\Application\CommandHandler\CommandHandlerInterface;
use App\Application\CommandHandler\PostbackHandlerInterface;
use App\Application\CommandHandler\CommandHandlerDispatcher;
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
        $dispatcher = new CommandHandlerDispatcher([$this->messageHandlerMock], [$this->postbackHandlerMock]);

        $this->chatService = new ChatApplicationService(
            $this->bot,
            $this->commandAndTriggerServiceMock,
            $dispatcher
        );
    }

    public function test_handleMessage_delegates_to_handler_and_logs(): void
    {
        $loggerMock = $this->createMock(\App\Infrastructure\Logger\Logger::class);
        $dispatcher = new CommandHandlerDispatcher([$this->messageHandlerMock], [$this->postbackHandlerMock]);
        $chatService = new ChatApplicationService(
            $this->bot,
            $this->commandAndTriggerServiceMock,
            $dispatcher,
            $loggerMock
        );

        $this->commandAndTriggerServiceMock->method('judgeCommand')->willReturn(Command::Other);
        $loggerMock->expects($this->once())->method('log')->with($this->stringContains('Judged Command: 9'));

        $this->messageHandlerMock->method('canHandle')->with(Command::Other)->willReturn(true);
        $this->messageHandlerMock->expects($this->once())
            ->method('handle')
            ->with($this->callback(function (Message $message) {
                return $message->getContent() === "hello" && !$message->isSystem();
            }), $this->bot, Command::Other)
            ->willReturn(new BotResponse("hi"));

        $response = $chatService->handleMessage("hello");
        $this->assertSame("hi", $response->getText());
    }

    public function test_handleMessage_selects_correct_handler_from_multiple(): void
    {
        $handler1 = $this->createMock(CommandHandlerInterface::class);
        $handler1->method('canHandle')->willReturn(false);

        $handler2 = $this->createMock(CommandHandlerInterface::class);
        $handler2->method('canHandle')->with(Command::ShowHelp)->willReturn(true);
        $handler2->expects($this->once())
            ->method('handle')
            ->willReturn(new BotResponse("help content"));

        $dispatcher = new CommandHandlerDispatcher([$handler1, $handler2], []);
        $chatService = new ChatApplicationService(
            $this->bot,
            $this->commandAndTriggerServiceMock,
            $dispatcher
        );

        $this->commandAndTriggerServiceMock->method('judgeCommand')->willReturn(Command::ShowHelp);

        $response = $chatService->handleMessage("help");
        $this->assertSame("help content", $response->getText());
    }

    public function test_handleMessage_throws_exception_if_no_handler_found(): void
    {
        $this->commandAndTriggerServiceMock->method('judgeCommand')->willReturn(Command::Other);
        $this->messageHandlerMock->method('canHandle')->willReturn(false);

        $this->expectException(HandlerNotFoundException::class);
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

        $this->expectException(HandlerNotFoundException::class);
        $this->expectExceptionMessage("Unsupported postback command: unknown");

        $this->chatService->handlePostback($data);
    }

    public function test_getLineTarget_returns_test_in_testing_env(): void
    {
        // CFUtils::isTestingEnv() is mocked by environment variable or usually returns true in tests
        $this->assertSame('test', $this->chatService->getLineTarget());
    }

    public function test_handleTrigger_bypasses_command_judgment(): void
    {
        $trigger = new TimerTrigger("today", "12:00", "reminder request");

        // judgeCommand should NOT be called
        $this->commandAndTriggerServiceMock->expects($this->never())
            ->method('judgeCommand');

        $this->messageHandlerMock->method('canHandle')->with(Command::Other)->willReturn(true);
        $this->messageHandlerMock->expects($this->once())
            ->method('handle')
            ->with($this->callback(function (Message $message) {
                return str_contains($message->getContent(), "reminder request") && $message->isSystem();
            }), $this->bot, Command::Other)
            ->willReturn(new BotResponse("triggered response"));

        $response = $this->chatService->handleTrigger($trigger);
        $this->assertSame("triggered response", $response->getText());
    }
}
