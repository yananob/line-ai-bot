<?php

declare(strict_types=1);

namespace Tests\Application\Config;

use App\Application\Config\ConfigApplicationService;
use App\Domain\Config\ConfigRepository;
use PHPUnit\Framework\TestCase;

final class ConfigApplicationServiceTest extends TestCase
{
    private $repositoryMock;
    private ConfigApplicationService $service;
    private string $viewsPath;
    private string $cachePath;

    protected function setUp(): void
    {
        $this->repositoryMock = $this->createMock(ConfigRepository::class);
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
        $this->repositoryMock->expects($this->once())
            ->method('findAllConfigs')
            ->willReturn(['bot_1' => ['bot_name' => 'Bot 1']]);

        $html = $this->service->renderIndex();
        $this->assertIsString($html);
        $this->assertStringContainsString('Bot 1', $html);
    }

    public function test_renderEditはbotIdが指定された場合にリポジトリを呼び出す(): void
    {
        $botId = 'test-bot';
        $this->repositoryMock->expects($this->once())
            ->method('findBotConfig')
            ->with($botId)
            ->willReturn(['bot_name' => 'Test Bot']);

        $html = $this->service->renderEdit($botId);
        $this->assertIsString($html);
        $this->assertStringContainsString('Test Bot', $html);
    }

    public function test_renderTriggersはリポジトリを呼び出す(): void
    {
        $botId = 'test-bot';
        $this->repositoryMock->expects($this->once())
            ->method('findBotConfig')
            ->willReturn(['bot_name' => 'Test Bot']);
        $this->repositoryMock->expects($this->once())
            ->method('findTriggers')
            ->with($botId)
            ->willReturn([['id' => 'trigger_1', 'request' => 'Hello']]);

        $html = $this->service->renderTriggers($botId);
        $this->assertIsString($html);
        $this->assertStringContainsString('Test Bot', $html);
        $this->assertStringContainsString('Hello', $html);
    }

    public function test_renderTriggerEditはリポジトリを呼び出す(): void
    {
        $botId = 'test-bot';
        $triggerId = 'trigger-1';
        $this->repositoryMock->expects($this->once())
            ->method('findBotConfig')
            ->willReturn(['bot_name' => 'Test Bot']);
        $this->repositoryMock->expects($this->once())
            ->method('findTrigger')
            ->with($botId, $triggerId)
            ->willReturn(['id' => $triggerId, 'request' => 'Hello']);

        $html = $this->service->renderTriggerEdit($botId, $triggerId);
        $this->assertIsString($html);
        $this->assertStringContainsString('Test Bot', $html);
        $this->assertStringContainsString('Hello', $html);
    }

    public function test_saveBotConfigはリポジトリを呼び出す(): void
    {
        $botId = 'test-bot';
        $data = ['bot_name' => 'New Name'];
        $this->repositoryMock->expects($this->once())
            ->method('saveBotConfig')
            ->with($botId, $data);

        $this->service->saveBotConfig($botId, $data);
    }

    public function test_saveTriggerはリポジトリを呼び出す(): void
    {
        $botId = 'test-bot';
        $triggerId = 'trigger-1';
        $data = ['request' => 'New Trigger'];
        $this->repositoryMock->expects($this->once())
            ->method('saveTrigger')
            ->with($botId, $triggerId, $data);

        $this->service->saveTrigger($botId, $triggerId, $data);
    }

    public function test_deleteTriggerはリポジトリを呼び出す(): void
    {
        $botId = 'test-bot';
        $triggerId = 'trigger-1';
        $this->repositoryMock->expects($this->once())
            ->method('deleteTrigger')
            ->with($botId, $triggerId);

        $this->service->deleteTrigger($botId, $triggerId);
    }

    public function test_deleteBotはリポジトリを呼び出す(): void
    {
        $botId = 'test-bot';
        $this->repositoryMock->expects($this->once())
            ->method('deleteBot')
            ->with($botId);

        $this->service->deleteBot($botId);
    }
}
