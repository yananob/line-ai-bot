<?php

declare(strict_types=1);

namespace MyApp;

use Google\Cloud\Firestore\CollectionReference;
use Google\Cloud\Firestore\DocumentSnapshot;

class BotConfig
{
    private ?array $config;     // memo: 初めてのやり取りの際は空になる
    private string $collectionId;

    public function __construct(private CollectionReference $collectionReference, private ?BotConfig $configDefault)
    {
        $this->collectionId = $collectionReference->id();
        $this->config = $collectionReference->document("config")->snapshot()->data();
    }

    public function getId(): string
    {
        return $this->collectionId;
    }

    private function __getConfig(string $fieldName, bool $useDefaultAndGenerated): array
    {
        $result = [];
        if (!empty($this->config[$fieldName])) {
            array_push($result, ...$this->config[$fieldName]);
        }
        if ((empty($result) || $useDefaultAndGenerated) && !empty($this->configDefault)) {
            // TODO: ダサい
            if ($fieldName === "bot_characteristics") {
                array_push($result, ...$this->configDefault->getBotCharacteristics());
            } elseif ($fieldName === "human_characteristics") {
                array_push($result, ...$this->configDefault->getHumanCharacteristics());
            } else {
                array_push($result, ...$this->configDefault->getRequests());
            }
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

    public function getTriggers(): array
    {
        $result = [];
        foreach ($this->collectionReference->document("triggers")->collection("triggers") as $triggerDoc) {
            $data = $triggerDoc->snapshot()->data();
            $trigger = new \stdClass();
            foreach (["event", "date", "time", "request"] as $key) {
                $trigger->$key = $data[$key];
            }
            $result[] = $trigger;
        }
        return $result;
    }

    // public function getMode(): string
    // {
    //     return empty($this->config["mode"]) ? $this->configDefault->getMode() : $this->config["mode"];
    // }
    // public function isChatMode(): bool
    // {
    //     return $this->getMode() === Mode::Chat->value;
    // }

    // public function isConsultingMode(): bool
    // {
    //     return $this->getMode() === Mode::Consulting->value;
    // }

    public function getLineTarget(): string
    {
        return empty($this->config["line_target"]) ? $this->configDefault->getLineTarget() : $this->config["line_target"];
    }
}
