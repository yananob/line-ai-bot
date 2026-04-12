<?php

declare(strict_types=1);

namespace Tests\Application\Config;

use App\Application\Config\ConfigApplicationService;
use App\Domain\Bot\Bot;
use App\Domain\Bot\BotRepository;
use App\Domain\Bot\Trigger\TimerTrigger;
use PHPUnit\Framework\TestCase;

final class ConfigApplicationServiceTest extends TestCase
{
    private $repositoryMock;
    private ConfigApplicationService $service;
    private string $viewsPath;
    private string $cachePath;

    protected function setUp(): void
    {
        $this->repositoryMock = $this->createMock(BotRepository::class);
        $this->viewsPath = __DIR__ . '/../../../views';
        $this->cachePath = '/tmp/bladeone_cache_test';

        $this->service = new ConfigApplicationService(
            $this->repositoryMock,
            $this->viewsPath,
            $this->cachePath
        );
    }

    public function test_renderIndexはリポジトリを呼び出しHTMLを返す(): void
    {
        $bot = new Bot('bot_1');
        $bot->setName('Bot 1');

        $this->repositoryMock->expects($this->once())
            ->method('getAllUserBots')
            ->willReturn([$bot]);

        $html = $this->service->renderIndex();
        $this->assertIsString($html);
        $this->assertStringContainsString('Bot 1', $html);
    }

    public function test_renderEditはbotIdが指定された場合にリポジトリを呼び出す(): void
    {
        $botId = 'test-bot';
        $bot = new Bot($botId);
        $bot->setName('Test Bot');

        $this->repositoryMock->expects($this->once())
            ->method('findById')
            ->with($botId)
            ->willReturn($bot);

        $html = $this->service->renderEdit($botId);
        $this->assertIsString($html);
        $this->assertStringContainsString('Test Bot', $html);
    }

    public function test_renderTriggersはリポジトリを呼び出す(): void
    {
        $botId = 'test-bot';
        $bot = new Bot($botId);
        $bot->setName('Test Bot');
        $trigger = new TimerTrigger('today', '12:00', 'Hello');
        $bot->setTrigger('trigger_1', $trigger);

        $this->repositoryMock->expects($this->once())
            ->method('findById')
            ->with($botId)
            ->willReturn($bot);

        $html = $this->service->renderTriggers($botId);
        $this->assertIsString($html);
        $this->assertStringContainsString('Test Bot', $html);
        $this->assertStringContainsString('Hello', $html);
    }

    public function test_renderTriggerEditはリポジトリを呼び出す(): void
    {
        $botId = 'test-bot';
        $triggerId = 'trigger-1';
        $bot = new Bot($botId);
        $bot->setName('Test Bot');
        $trigger = new TimerTrigger('today', '12:00', 'Hello');
        $bot->setTrigger($triggerId, $trigger);

        $this->repositoryMock->expects($this->once())
            ->method('findById')
            ->with($botId)
            ->willReturn($bot);

        $html = $this->service->renderTriggerEdit($botId, $triggerId);
        $this->assertIsString($html);
        $this->assertStringContainsString('Test Bot', $html);
        $this->assertStringContainsString('Hello', $html);
    }

    public function test_saveBotConfigはリポジトリを呼び出す(): void
    {
        $botId = 'test-bot';
        $data = ['bot_name' => 'New Name'];
        $bot = new Bot($botId);

        $this->repositoryMock->expects($this->once())
            ->method('findOrDefault')
            ->with($botId)
            ->willReturn($bot);

        $this->repositoryMock->expects($this->once())
            ->method('save')
            ->with($this->callback(function($savedBot) use ($botId) {
                return $savedBot->getId() === $botId && $savedBot->getName() === 'New Name';
            }));

        $this->service->saveBotConfig($botId, $data);
    }

    public function test_saveTriggerはリポジトリを呼び出す(): void
    {
        $botId = 'test-bot';
        $triggerId = 'trigger-1';
        $data = ['request' => 'New Trigger', 'date' => 'today', 'time' => '12:00'];
        $bot = new Bot($botId);

        $this->repositoryMock->expects($this->once())
            ->method('findById')
            ->with($botId)
            ->willReturn($bot);

        $this->repositoryMock->expects($this->once())
            ->method('save')
            ->with($bot);

        $this->service->saveTrigger($botId, $triggerId, $data);
        $this->assertEquals('New Trigger', $bot->getTriggerById($triggerId)->getRequest());
    }

    public function test_deleteTriggerはリポジトリを呼び出す(): void
    {
        $botId = 'test-bot';
        $triggerId = 'trigger-1';
        $bot = new Bot($botId);
        $trigger = new TimerTrigger('today', '12:00', 'Hello');
        $bot->setTrigger($triggerId, $trigger);

        $this->repositoryMock->expects($this->once())
            ->method('findById')
            ->with($botId)
            ->willReturn($bot);

        $this->repositoryMock->expects($this->once())
            ->method('save')
            ->with($bot);

        $this->service->deleteTrigger($botId, $triggerId);
        $this->assertNull($bot->getTriggerById($triggerId));
    }

    public function test_deleteBotはリポジトリを呼び出す(): void
    {
        $botId = 'test-bot';
        $this->repositoryMock->expects($this->once())
            ->method('delete')
            ->with($botId);

        $this->service->deleteBot($botId);
    }
}
