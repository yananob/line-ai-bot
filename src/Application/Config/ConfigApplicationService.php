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
        $botIds = $this->configRepository->findAllBotIds();
        return $this->blade->run("config.index", [
            "botIds" => $botIds,
            "basePath" => $this->basePath
        ]);
    }

    public function renderEdit(?string $botId = null): string
    {
        $data = null;
        $triggers = [];
        if ($botId !== null) {
            $data = $this->configRepository->findBotConfig($botId);
            $triggers = $this->configRepository->findTriggers($botId);
        }

        $dataJson = $data ? json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '{}';

        return $this->blade->run("config.edit", [
            "botId" => $botId,
            "dataJson" => $dataJson,
            "triggers" => $triggers,
            "basePath" => $this->basePath
        ]);
    }

    public function saveBotConfig(string $botId, string $jsonContent): void
    {
        $data = json_decode($jsonContent, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException("Invalid JSON format: " . json_last_error_msg());
        }

        $this->configRepository->saveBotConfig($botId, $data);
    }

    public function saveTrigger(string $botId, string $triggerId, string $jsonContent): void
    {
        $data = json_decode($jsonContent, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException("Invalid JSON format: " . json_last_error_msg());
        }

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
