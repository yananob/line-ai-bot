<?php declare(strict_types=1);

namespace App\Application\Config;

use App\Domain\Config\ConfigRepository;
use eftec\bladeone\BladeOne;

class ConfigApplicationService
{
    private BladeOne $blade;
    private string $basePath = '';

    public function __construct(
        private ConfigRepository $configRepository,
        string $viewsPath,
        string $cachePath
    ) {
        if (!is_dir($cachePath)) {
            mkdir($cachePath, 0777, true);
        }
        $this->blade = new BladeOne($viewsPath, $cachePath, BladeOne::MODE_AUTO);
    }

    public function setBasePath(string $basePath): void
    {
        $this->basePath = $basePath;
    }

    public function renderIndex(): string
    {
        $bots = $this->configRepository->findAllConfigs();
        return $this->blade->run("config.index", [
            "bots" => $bots,
            "basePath" => $this->basePath
        ]);
    }

    public function renderEdit(?string $botId = null): string
    {
        $data = [];
        if ($botId !== null) {
            $data = $this->configRepository->findBotConfig($botId) ?? [];
        }

        $botName = $data['bot_name'] ?? '';
        $botChars = $data['bot_characteristics'] ?? [];
        $humanChars = $data['human_characteristics'] ?? [];
        $requests = $data['requests'] ?? [];
        $lineTarget = $data['line_target'] ?? '';

        return $this->blade->run("config.edit", [
            "botId" => $botId,
            "botName" => $botName,
            "botChars" => $botChars,
            "humanChars" => $humanChars,
            "requests" => $requests,
            "lineTarget" => $lineTarget,
            "basePath" => $this->basePath
        ]);
    }

    public function renderTriggers(string $botId): string
    {
        $data = $this->configRepository->findBotConfig($botId) ?? [];
        $botName = $data['bot_name'] ?? '';
        $triggers = $this->configRepository->findTriggers($botId);
        return $this->blade->run("config.triggers", [
            "botId" => $botId,
            "botName" => $botName,
            "triggers" => $triggers,
            "basePath" => $this->basePath
        ]);
    }

    public function renderTriggerEdit(string $botId, ?string $triggerId = null): string
    {
        $data = $this->configRepository->findBotConfig($botId) ?? [];
        $botName = $data['bot_name'] ?? '';
        $trigger = null;
        if ($triggerId !== null) {
            $trigger = $this->configRepository->findTrigger($botId, $triggerId);
        }

        return $this->blade->run("config.trigger_edit", [
            "botId" => $botId,
            "botName" => $botName,
            "triggerId" => $triggerId,
            "trigger" => $trigger,
            "basePath" => $this->basePath
        ]);
    }

    public function saveBotConfig(string $botId, array $data): void
    {
        $this->configRepository->saveBotConfig($botId, $data);
    }

    public function saveTrigger(string $botId, string $triggerId, array $data): void
    {
        $this->configRepository->saveTrigger($botId, $triggerId, $data);
    }

    public function deleteTrigger(string $botId, string $triggerId): void
    {
        $this->configRepository->deleteTrigger($botId, $triggerId);
    }

    public function deleteBot(string $botId): void
    {
        $this->configRepository->deleteBot($botId);
    }
}
