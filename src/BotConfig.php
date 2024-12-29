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

    private function __getConfig(string $fieldName, bool $useDefaultToo): array
    {
        $result = [];
        if (!empty($this->config[$fieldName])) {
            array_push($result, ...$this->config[$fieldName]);
        }
        if ((empty($result) || $useDefaultToo) && !empty($this->configDefault)) {
            // TODO: ダサい
            if ($fieldName === "bot_characteristics") {
                array_push($result, ...$this->configDefault->getBotCharacteristics());
            } elseif ($fieldName === "human_characteristics") {
                array_push($result, ...$this->configDefault->getHumanCharacteristics());
            } else {
                array_push($result, ...$this->configDefault->getConfigRequests());
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
    public function getConfigRequests(bool $useDefaultToo = true): array
    {
        return $this->__getConfig("requests", $useDefaultToo);
    }

    public function getLineTarget(): string
    {
        return empty($this->config["line_target"]) ? $this->configDefault->getLineTarget() : $this->config["line_target"];
    }

    public function getTriggers(): array
    {
        $result = [];
        foreach ($this->collectionReference->document("triggers")->collection("triggers")->documents() as $triggerDoc) {
            $data = $triggerDoc->data();
            switch ($data["event"]) {
                case "timer":
                    $trigger = new TimerTrigger($data["date"], $data["time"], $data["request"]);
                    break;

                default:
                    throw new \Exception(("Unsupported event: " . $data["event"]));
            }
            $result[] = $trigger;
        }
        return $result;
    }

    public function getTriggerRequests(): array
    {
        $data = $this->collectionReference->document("triggers")->snapshot()->data();
        return empty($data["requests"]) ? $this->configDefault->getTriggerRequests() : $data["requests"];
    }

    public function addTrigger(Trigger $trigger): void
    {
        if ($trigger instanceof TimerTrigger) {
            $doc = [
                "event" => $trigger->getEvent(),
                "date" => $trigger->getDate(),
                "time" => $trigger->getTime(),
                "request" => $trigger->getRequest(),
            ];
        } else {
            throw new \Exception("Unsupported trigger: " . var_export($trigger));
        }

        $this->collectionReference->document("triggers")->collection("triggers")->add($doc);
    }
}
