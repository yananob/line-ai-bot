<?php declare(strict_types=1);

namespace App\Application\Config;

use App\Domain\Bot\BotRepository;
use App\Domain\Bot\Trigger\TimerTrigger;
use eftec\bladeone\BladeOne;

class ConfigApplicationService
{
    private BladeOne $blade;
    private string $basePath = '';

    public function __construct(
        private BotRepository $botRepository,
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
        $botConfigs = [];
        foreach ($this->botRepository->getAllUserBots() as $bot) {
            $botConfigs[$bot->getId()] = [
                'bot_name' => $bot->getName(),
            ];
        }
        // Also include default bot
        try {
            $defaultBot = $this->botRepository->findDefault();
            $botConfigs['default'] = [
                'bot_name' => $defaultBot->getName() ?: 'default',
            ];
        } catch (\Exception $e) {
            // Default might not exist yet
        }

        return $this->blade->run("config.index", [
            "bots" => $botConfigs,
            "basePath" => $this->basePath
        ]);
    }

    public function renderEdit(?string $botId = null): string
    {
        $botName = '';
        $botChars = [];
        $humanChars = [];
        $requests = [];
        $lineTarget = '';

        if ($botId !== null) {
            try {
                $bot = ($botId === 'default')
                    ? $this->botRepository->findDefault()
                    : $this->botRepository->findById($botId);

                $botName = $bot->getName();
                $botChars = $bot->getPersonality()->getBotCharacteristics()->toArray();
                $humanChars = $bot->getPersonality()->getHumanCharacteristics()->toArray();
                $requests = $bot->getConfigRequests(true, false)->toArray();
                $lineTarget = $bot->getLineTarget();
            } catch (\Exception $e) {
                // Bot not found, use defaults
            }
        }

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
        $bot = ($botId === 'default')
            ? $this->botRepository->findDefault()
            : $this->botRepository->findById($botId);

        $triggersData = [];
        foreach ($bot->getTriggers() as $trigger) {
            $triggersData[$trigger->getId()] = $trigger->toArray();
        }

        return $this->blade->run("config.triggers", [
            "botId" => $botId,
            "botName" => $bot->getName(),
            "triggers" => $triggersData,
            "basePath" => $this->basePath
        ]);
    }

    public function renderTriggerEdit(string $botId, ?string $triggerId = null): string
    {
        $bot = ($botId === 'default')
            ? $this->botRepository->findDefault()
            : $this->botRepository->findById($botId);

        $triggerData = null;
        if ($triggerId !== null) {
            $trigger = $bot->getTriggerById($triggerId);
            if ($trigger) {
                $triggerData = $trigger->toArray();
            }
        }

        return $this->blade->run("config.trigger_edit", [
            "botId" => $botId,
            "botName" => $bot->getName(),
            "triggerId" => $triggerId,
            "trigger" => $triggerData,
            "basePath" => $this->basePath
        ]);
    }

    public function saveBotConfig(string $botId, array $data): void
    {
        $bot = $this->botRepository->findOrDefault($botId);
        $bot->setName($data['bot_name'] ?? '');
        $bot->setBotCharacteristics($data['bot_characteristics'] ?? []);
        $bot->setHumanCharacteristics($data['human_characteristics'] ?? []);
        $bot->setConfigRequests($data['requests'] ?? []);
        $bot->setLineTarget($data['line_target'] ?? '');
        $this->botRepository->save($bot);
    }

    public function saveTrigger(string $botId, string $triggerId, array $data): void
    {
        $bot = ($botId === 'default')
            ? $this->botRepository->findDefault()
            : $this->botRepository->findById($botId);

        $trigger = new TimerTrigger(
            (string)($data['date'] ?? ''),
            (string)($data['time'] ?? ''),
            (string)($data['request'] ?? '')
        );
        $bot->setTrigger($triggerId, $trigger);
        $this->botRepository->save($bot);
    }

    public function deleteTrigger(string $botId, string $triggerId): void
    {
        $bot = ($botId === 'default')
            ? $this->botRepository->findDefault()
            : $this->botRepository->findById($botId);

        $bot->deleteTriggerById($triggerId);
        $this->botRepository->save($bot);
    }

    public function deleteBot(string $botId): void
    {
        $this->botRepository->delete($botId);
    }
}
