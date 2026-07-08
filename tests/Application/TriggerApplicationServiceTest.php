<?php

declare(strict_types=1);

namespace Tests\Application;

use App\Application\BotResponse;
use App\Application\ChatApplicationService;
use App\Application\TriggerApplicationService;
use App\Domain\Bot\Bot;
use App\Domain\Bot\BotRepository;
use App\Domain\Bot\Trigger\TimerTrigger;
use App\Infrastructure\DependencyInjection\Container;
use App\Infrastructure\Line\LineClient;
use App\Infrastructure\Logger\Logger;
use PHPUnit\Framework\TestCase;

final class TriggerApplicationServiceTest extends TestCase
{
    private $botRepositoryMock;
    private $lineClientMock;
    private $containerMock;
    private $loggerMock;
    private TriggerApplicationService $service;

    protected function setUp(): void
    {
        $this->botRepositoryMock = $this->createMock(BotRepository::class);
        $this->lineClientMock = $this->createMock(LineClient::class);
        $this->containerMock = $this->createMock(Container::class);
        $this->loggerMock = $this->createMock(Logger::class);

        $this->service = new TriggerApplicationService(
            $this->botRepositoryMock,
            $this->lineClientMock,
            $this->containerMock,
            $this->loggerMock
        );
    }

    public function test_executeTriggers_processes_eligible_triggers(): void
    {
        $bot = $this->createMock(Bot::class);
        $bot->method('getId')->willReturn('bot_1');

        $trigger = $this->createMock(TimerTrigger::class);
        $trigger->method('getEvent')->willReturn('timer');
        $trigger->method('shouldRunNow')->with(10)->willReturn(true);
        $trigger->method('getDate')->willReturn('2023-10-27');
        $trigger->method('getTime')->willReturn('10:00');
        $trigger->method('getRequest')->willReturn('test request');

        $bot->method('getTriggers')->willReturn(['t1' => $trigger]);
        $this->botRepositoryMock->method('getAllUserBots')->willReturn([$bot]);

        $chatAppServiceMock = $this->createMock(ChatApplicationService::class);
        $chatAppServiceMock->method('getLineTarget')->willReturn('test_target');
        $chatAppServiceMock->method('handleTrigger')->with($trigger)->willReturn(new BotResponse('bot response'));

        $this->containerMock->method('createChatApplicationService')->with($bot)->willReturn($chatAppServiceMock);

        $this->lineClientMock->expects($this->once())
            ->method('sendPush')
            ->with('test_target', null, 'bot_1', 'bot response');

        $this->service->executeTriggers();
    }

    public function test_executeTriggers_skips_non_timer_triggers(): void
    {
        $bot = $this->createMock(Bot::class);
        $bot->method('getId')->willReturn('bot_1');

        $nonTimerTrigger = new \stdClass();

        $bot->method('getTriggers')->willReturn(['t1' => $nonTimerTrigger]);
        $this->botRepositoryMock->method('getAllUserBots')->willReturn([$bot]);

        $this->loggerMock->expects($this->atLeastOnce())
            ->method('log')
            ->with($this->stringContains("Skipping trigger for user bot_1 as it's not a TimerTrigger."));

        $this->service->executeTriggers();
    }

    public function test_executeTriggers_skips_triggers_not_running_now(): void
    {
        $bot = $this->createMock(Bot::class);
        $trigger = $this->createMock(TimerTrigger::class);
        $trigger->method('getEvent')->willReturn('timer');
        $trigger->method('shouldRunNow')->willReturn(false);

        $bot->method('getTriggers')->willReturn(['t1' => $trigger]);
        $this->botRepositoryMock->method('getAllUserBots')->willReturn([$bot]);

        $this->containerMock->expects($this->never())->method('createChatApplicationService');
        $this->lineClientMock->expects($this->never())->method('sendPush');

        $this->service->executeTriggers();
        $this->assertTrue(true);
    }

    public function test_executeTriggers_handles_initialization_failure(): void
    {
        $bot = $this->createMock(Bot::class);
        $bot->method('getId')->willReturn('bot_1');
        $trigger = $this->createMock(TimerTrigger::class);
        $trigger->method('getEvent')->willReturn('timer');
        $trigger->method('shouldRunNow')->willReturn(true);

        $bot->method('getTriggers')->willReturn(['t1' => $trigger]);
        $this->botRepositoryMock->method('getAllUserBots')->willReturn([$bot]);

        $this->containerMock->method('createChatApplicationService')
            ->willThrowException(new \Exception('init error'));

        $messages = [];
        $this->loggerMock->method('log')->willReturnCallback(function ($msg) use (&$messages) {
            $messages[] = $msg;
        });

        $this->service->executeTriggers();

        $this->assertContains('Executing Trigger for bot bot_1: Date=, Time=, Request=', $messages);
        $this->assertStringContainsString('TRIGGER: Failed to initialize ChatApplicationService for user bot_1: init error', implode("\n", $messages));
    }
}
