<?php

declare(strict_types=1);

namespace MyApp\Tests\Application\CommandHandler;

use MyApp\Application\CommandHandler\RemoveTriggerPostbackHandler;
use MyApp\Domain\Bot\Bot;
use MyApp\Domain\Bot\BotRepository;
use MyApp\Domain\Bot\Trigger\TimerTrigger;
use MyApp\Domain\Bot\Consts;
use PHPUnit\Framework\TestCase;

final class RemoveTriggerPostbackHandlerTest extends TestCase
{
    public function test_canHandle(): void
    {
        $repoMock = $this->createMock(BotRepository::class);
        $handler = new RemoveTriggerPostbackHandler($repoMock);
        $this->assertTrue($handler->canHandle(Consts::CMD_REMOVE_TRIGGER));
        $this->assertFalse($handler->canHandle("unknown"));
    }

    public function test_handle(): void
    {
        $repoMock = $this->createMock(BotRepository::class);
        $handler = new RemoveTriggerPostbackHandler($repoMock);

        $bot = new Bot("test");
        $trigger = new TimerTrigger("today", "12:00", "test");
        $triggerId = $bot->addTrigger($trigger);

        $repoMock->expects($this->once())->method('save')->with($bot);

        $params = ["id" => $triggerId, "trigger" => "today 12:00 test"];
        $response = $handler->handle($params, $bot);

        $this->assertSame("削除しました：today 12:00 test", $response->getText());
        $this->assertArrayNotHasKey($triggerId, $bot->getTriggers());
    }
}
