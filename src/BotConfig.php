<?php

declare(strict_types=1);

namespace MyApp;

use Google\Cloud\Firestore\CollectionReference;
use Google\Cloud\Firestore\DocumentSnapshot;

class BotConfig
{
    private ?array $config;     // memo: 最初のやり取りの際は空になる
    // private DocumentReference $triggersGenerated;
    // private ?string $mode;

    public function __construct(CollectionReference $collectionReference, private ?BotConfig $configDefault)
    {
        $this->config = $collectionReference->document("config")->snapshot()->data();
    }

    private function __getConfig(string $fieldName, bool $useDefaultAndGenerated): array
    {
        $result = [];
        if (!empty($this->config[$fieldName])) {
            array_push($result, $this->config[$fieldName]);
        }
        if ((empty($result) || $useDefaultAndGenerated) && !empty($configDefault)) {
            array_push($result, $this->configDefault[$fieldName]);
        }
        return $result;
    }

    public function getBotCharacteristics(): array
    {
        return $this->__getConfig("bot_characteristics", false);
    }
    public function getHumanCharacteristics(): array
    {
        return $this->__getConfig("human_characteristics", false);
    }
    public function hasHumanCharacteristics(): bool
    {
        return (!empty($this->getHumanCharacteristics()));
    }
    public function getRequests(): array
    {
        return $this->__getConfig("requests", true);
    }

    public function getMode(): string
    {
        return empty($this->config["mode"]) ? $this->configDefault->getMode() : $this->config["mode"];
    }
    public function isChatMode(): bool
    {
        return $this->getMode() === Mode::Chat->value;
    }

    public function isConsultingMode(): bool
    {
        return $this->getMode() === Mode::Consulting->value;
    }

    public function getLineTarget(): string
    {
        return $this->configDefault["line_target"];
    }
}
