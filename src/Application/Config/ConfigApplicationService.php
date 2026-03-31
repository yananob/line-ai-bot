<?php declare(strict_types=1);

namespace App\Application\Config;

use App\Domain\Config\Config;
use App\Domain\Config\ConfigRepository;
use eftec\bladeone\BladeOne;

class ConfigApplicationService
{
    private BladeOne $blade;

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

    public function renderIndex(): string
    {
        $configs = $this->configRepository->findAll();
        return $this->blade->run("config.index", ["configs" => $configs]);
    }

    public function renderEdit(?string $id = null): string
    {
        $config = null;
        if ($id !== null) {
            $config = $this->configRepository->findById($id);
        }

        // If config is null, it means we are creating a new one
        $dataJson = $config ? json_encode($config->getData(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '{}';

        return $this->blade->run("config.edit", [
            "config" => $config,
            "dataJson" => $dataJson,
            "id" => $id
        ]);
    }

    public function saveConfig(string $id, string $jsonContent): void
    {
        $data = json_decode($jsonContent, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException("Invalid JSON format: " . json_last_error_msg());
        }

        $config = new Config($id, $data);
        $this->configRepository->save($config);
    }

    public function deleteConfig(string $id): void
    {
        $this->configRepository->delete($id);
    }
}
