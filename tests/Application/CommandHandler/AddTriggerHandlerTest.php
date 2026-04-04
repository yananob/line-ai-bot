<?php

declare(strict_types=1);

namespace Tests\Application\CommandHandler;

use App\Application\CommandHandler\AddTriggerHandler;
use App\Domain\Bot\ValueObject\Command;
use App\Domain\Bot\Bot;
use App\Domain\Bot\BotRepository;
use App\Domain\Bot\Service\CommandAndTriggerService;
use App\Domain\Bot\Trigger\TimerTrigger;
use App\Domain\Bot\ValueObject\Message;
use PHPUnit\Framework\TestCase;

final class AddTriggerHandlerTest extends TestCase
{
    public function test_canHandle(): void
    {
        $cmdServiceMock = $this->createMock(CommandAndTriggerService::class);
        $repoMock = $this->createMock(BotRepository::class);
        $handler = new AddTriggerHandler($cmdServiceMock, $repoMock);

        $this->assertTrue($handler->canHandle(Command::AddOneTimeTrigger));
        $this->assertTrue($handler->canHandle(Command::AddDailyTrigger));
        $this->assertFalse($handler->canHandle(Command::Other));
    }

    public function test_handle_OneTimeTrigger(): void
    {
        $cmdServiceMock = $this->createMock(CommandAndTriggerService::class);
        $repoMock = $this->createMock(BotRepository::class);
        $handler = new AddTriggerHandler($cmdServiceMock, $repoMock);

        $bot = new Bot("test");
        $trigger = new TimerTrigger("today", "12:00", "test");

        $cmdServiceMock->method('generateOneTimeTrigger')->willReturn($trigger);
        $repoMock->expects($this->once())->method('save')->with($bot);

        $message = new Message("today 12:00 test", false);
        $response = $handler->handle($message, $bot, Command::AddOneTimeTrigger);
        $this->assertSame("タイマーを追加しました：" . $trigger, $response->getText());
        $this->assertCount(1, $bot->getTriggers());
    }

    public function test_handle_DailyTrigger(): void
    {
        $cmdServiceMock = $this->createMock(CommandAndTriggerService::class);
        $repoMock = $this->createMock(BotRepository::class);
        $handler = new AddTriggerHandler($cmdServiceMock, $repoMock);

        $bot = new Bot("test");
        $trigger = new TimerTrigger("everyday", "12:00", "test");

        $cmdServiceMock->method('generateDailyTrigger')->willReturn($trigger);
        $repoMock->expects($this->once())->method('save')->with($bot);

        $message = new Message("everyday 12:00 test", false);
        $response = $handler->handle($message, $bot, Command::AddDailyTrigger);
        $this->assertSame("タイマーを追加しました：" . $trigger, $response->getText());
        $this->assertCount(1, $bot->getTriggers());
    }
}
