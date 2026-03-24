<?php

declare(strict_types=1);

namespace Tests\Application\CommandHandler;

use App\Application\CommandHandler\RemoveTriggerHandler;
use App\Domain\Bot\ValueObject\Command;
use App\Domain\Bot\Bot;
use App\Domain\Bot\Trigger\TimerTrigger;
use App\Domain\Bot\ValueObject\Message;
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

        $message = new Message("stop", false);
        $response = $handler->handle($message, $bot, Command::RemoveTrigger);
        $this->assertSame("どのタイマーを止めますか？", $response->getText());
        $this->assertNotNull($response->getQuickReply());
    }
}
