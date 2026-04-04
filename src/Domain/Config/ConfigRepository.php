<?php declare(strict_types=1);

namespace App\Domain\Config;

interface ConfigRepository
{
    /**
     * @return string[]
     */
    public function findAllBotIds(): array;

    /**
     * @return array[] botId => configData
     */
    public function findAllConfigs(): array;

    public function findBotConfig(string $botId): ?array;

    public function saveBotConfig(string $botId, array $data): void;

    /**
     * @return array[]
     */
    public function findTriggers(string $botId): array;

    public function findTrigger(string $botId, string $triggerId): ?array;

    public function saveTrigger(string $botId, string $triggerId, array $data): void;

    public function deleteTrigger(string $botId, string $triggerId): void;

    public function deleteBot(string $botId): void;
}
