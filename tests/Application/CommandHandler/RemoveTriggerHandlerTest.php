<?php

declare(strict_types=1);

namespace MyApp\Tests\Application\CommandHandler;

use MyApp\Application\CommandHandler\RemoveTriggerHandler;
use MyApp\Domain\Bot\ValueObject\Command;
use MyApp\Domain\Bot\Bot;
use MyApp\Domain\Bot\Trigger\TimerTrigger;
use PHPUnit\Framework\TestCase;

final class RemoveTriggerHandlerTest extends TestCase
{
    public function test_canHandle(): void
    {
        $handler = new RemoveTriggerHandler();
        $this->assertTrue($handler->canHandle(Command::RemoveTrigger));
        $this->assertFalse($handler->canHandle(Command::Other));
    }

    public function test_handle(): void
    {
        $handler = new RemoveTriggerHandler();
        $bot = new Bot("test");
        $bot->addTrigger(new TimerTrigger("today", "12:00", "test"));

        $response = $handler->handle("stop", $bot, Command::RemoveTrigger);
        $this->assertSame("どのタイマーを止めますか？", $response->getText());
        $this->assertNotNull($response->getQuickReply());
    }
}
