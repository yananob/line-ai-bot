<?php

declare(strict_types=1);

namespace Tests\Application\CommandHandler;

use App\Application\CommandHandler\RemoveTriggerPostbackHandler;
use App\Domain\Bot\Bot;
use App\Domain\Bot\BotRepository;
use App\Domain\Bot\Trigger\TimerTrigger;
use App\Domain\Bot\Consts;
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

    public function test_handle_throws_exception_if_trigger_id_not_found(): void
    {
        $repoMock = $this->createMock(BotRepository::class);
        $handler = new RemoveTriggerPostbackHandler($repoMock);

        $bot = new Bot("test");
        // No trigger added

        $this->expectException(\App\Domain\Exception\TriggerNotFoundException::class);
        $this->expectExceptionMessage("Trigger with ID 'non-existent' not found.");

        $params = ["id" => "non-existent", "trigger" => "some trigger"];
        $handler->handle($params, $bot);
    }
}
