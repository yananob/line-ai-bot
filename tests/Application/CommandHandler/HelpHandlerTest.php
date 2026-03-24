<?php

declare(strict_types=1);

namespace Tests\Application\CommandHandler;

use App\Application\CommandHandler\HelpHandler;
use App\Domain\Bot\ValueObject\Command;
use App\Domain\Bot\Bot;
use App\Domain\Bot\Messages;
use App\Domain\Bot\ValueObject\Message;
use PHPUnit\Framework\TestCase;

final class HelpHandlerTest extends TestCase
{
    public function test_canHandle(): void
    {
        $handler = new HelpHandler();
        $this->assertTrue($handler->canHandle(Command::ShowHelp));
        $this->assertFalse($handler->canHandle(Command::Other));
    }

    public function test_handle(): void
    {
        $handler = new HelpHandler();
        $bot = new Bot("test");
        $message = new Message("help", false);
        $response = $handler->handle($message, $bot, Command::ShowHelp);
        $this->assertSame(Messages::HELP, $response->getText());
    }
}
