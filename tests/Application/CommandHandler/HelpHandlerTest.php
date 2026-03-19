<?php

declare(strict_types=1);

namespace MyApp\Tests\Application\CommandHandler;

use MyApp\Application\CommandHandler\HelpHandler;
use MyApp\Domain\Bot\ValueObject\Command;
use MyApp\Domain\Bot\Bot;
use MyApp\Domain\Bot\Messages;
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
        $response = $handler->handle("help", $bot, Command::ShowHelp);
        $this->assertSame(Messages::HELP, $response->getText());
    }
}
