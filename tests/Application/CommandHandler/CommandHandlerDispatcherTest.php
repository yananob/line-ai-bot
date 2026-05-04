<?php

declare(strict_types=1);

namespace Tests\Application\CommandHandler;

use App\Application\CommandHandler\CommandHandlerDispatcher;
use App\Application\CommandHandler\CommandHandlerInterface;
use App\Application\CommandHandler\PostbackHandlerInterface;
use App\Application\BotResponse;
use App\Domain\Bot\Bot;
use App\Domain\Bot\ValueObject\Command;
use App\Domain\Bot\ValueObject\Message;
use App\Domain\Exception\HandlerNotFoundException;
use PHPUnit\Framework\TestCase;

final class CommandHandlerDispatcherTest extends TestCase
{
    private $messageHandlerMock;
    private $postbackHandlerMock;
    private CommandHandlerDispatcher $dispatcher;

    protected function setUp(): void
    {
        $this->messageHandlerMock = $this->createMock(CommandHandlerInterface::class);
        $this->postbackHandlerMock = $this->createMock(PostbackHandlerInterface::class);
        $this->dispatcher = new CommandHandlerDispatcher(
            [$this->messageHandlerMock],
            [$this->postbackHandlerMock]
        );
    }

    public function test_dispatchMessage_calls_handler_when_canHandle_returns_true(): void
    {
        $command = Command::Other;
        $message = new Message("hello", false);
        $bot = new Bot("test-bot");
        $expectedResponse = new BotResponse("response");

        $this->messageHandlerMock->method('canHandle')->with($command)->willReturn(true);
        $this->messageHandlerMock->expects($this->once())
            ->method('handle')
            ->with($message, $bot, $command)
            ->willReturn($expectedResponse);

        $response = $this->dispatcher->dispatchMessage($command, $message, $bot);
        $this->assertSame($expectedResponse, $response);
    }

    public function test_dispatchMessage_throws_exception_when_no_handler_can_handle(): void
    {
        $command = Command::Other;
        $message = new Message("hello", false);
        $bot = new Bot("test-bot");

        $this->messageHandlerMock->method('canHandle')->willReturn(false);

        $this->expectException(HandlerNotFoundException::class);
        $this->expectExceptionMessage("No handler found for command: 9");

        $this->dispatcher->dispatchMessage($command, $message, $bot);
    }

    public function test_dispatchPostback_calls_handler_when_canHandle_returns_true(): void
    {
        $commandValue = "test_cmd";
        $params = ["command" => $commandValue, "id" => "123"];
        $bot = new Bot("test-bot");
        $expectedResponse = new BotResponse("postback response");

        $this->postbackHandlerMock->method('canHandle')->with($commandValue)->willReturn(true);
        $this->postbackHandlerMock->expects($this->once())
            ->method('handle')
            ->with($params, $bot)
            ->willReturn($expectedResponse);

        $response = $this->dispatcher->dispatchPostback($commandValue, $params, $bot);
        $this->assertSame($expectedResponse, $response);
    }

    public function test_dispatchPostback_throws_exception_when_no_handler_can_handle(): void
    {
        $commandValue = "unknown";
        $params = ["command" => $commandValue];
        $bot = new Bot("test-bot");

        $this->postbackHandlerMock->method('canHandle')->willReturn(false);

        $this->expectException(HandlerNotFoundException::class);
        $this->expectExceptionMessage("Unsupported postback command: unknown");

        $this->dispatcher->dispatchPostback($commandValue, $params, $bot);
    }
}
