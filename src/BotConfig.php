<?php

declare(strict_types=1);

namespace MyApp;

use Google\Cloud\Firestore\CollectionReference;
use Google\Cloud\Firestore\DocumentSnapshot;
use MyApp\Domain\Bot\Trigger\Trigger; // New Trigger interface
use MyApp\Domain\Bot\Trigger\TimerTrigger; // New TimerTrigger class

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

    private function __getConfig(string $fieldName, bool $usePersonal, bool $useDefault): array
    {
        $result = [];
        if ($usePersonal && !empty($this->config[$fieldName])) {
            array_push($result, ...$this->config[$fieldName]);
        }
        if ($useDefault && !empty($this->configDefault)) {
            // TODO: ダサい
            if ($fieldName === "bot_characteristics") {
                array_push($result, ...$this->configDefault->getBotCharacteristics());
            } elseif ($fieldName === "human_characteristics") {
                array_push($result, ...$this->configDefault->getHumanCharacteristics());
            } else {
                array_push($result, ...$this->configDefault->getConfigRequests(usePersonal: true, useDefault: false));
            }
        }
        return $result;
    }

    public function getBotCharacteristics(): array
    {
        return $this->__getConfig("bot_characteristics", usePersonal: true, useDefault: false);
    }
    public function getHumanCharacteristics(): array
    {
        return $this->__getConfig("human_characteristics", usePersonal: true, useDefault: false);
    }
    public function hasHumanCharacteristics(): bool
    {
        return (!empty($this->getHumanCharacteristics()));
    }
    public function getConfigRequests(bool $usePersonal, bool $useDefault): array
    {
        return $this->__getConfig("requests",  usePersonal: $usePersonal, useDefault: $useDefault);
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
                    $trigger = new \MyApp\Domain\Bot\Trigger\TimerTrigger($data["date"], $data["time"], $data["request"]);
                    $trigger->setId($triggerDoc->id());
                    break;

                default:
                    // It's good practice to handle unknown event types.
                    // Depending on requirements, this could log an error, skip the trigger, or throw an exception.
                    // For now, let's assume we want to be strict and throw an exception.
                    throw new \Exception("Unsupported event type: " . $data["event"]);
            }
            $result[] = $trigger;
        }
        return $result; // This should return an array of Trigger interface compliant objects
    }

    public function getTriggerRequests(): array
    {
        $data = $this->collectionReference->document("triggers")->snapshot()->data();
        return empty($data["requests"]) ? $this->configDefault->getTriggerRequests() : $data["requests"];
    }

    /**
     * @return string Trigger Id
     */
    public function addTrigger(Trigger $trigger): string // Type hinted with the new Trigger interface
    {
        // Check if the trigger is an instance of the new TimerTrigger
        if ($trigger instanceof \MyApp\Domain\Bot\Trigger\TimerTrigger) {
            $doc = [
                "id"      => $trigger->getId(), // Persist ID if available, though Firestore generates one too
                "event"   => $trigger->getEvent(),
                "date"    => $trigger->getDate(),
                "time"    => $trigger->getTime(),
                "request" => $trigger->getRequest(),
            ];
        } else {
            // Handle other trigger types or throw an exception if only TimerTrigger is supported here.
            throw new \Exception("Unsupported trigger type: " . get_class($trigger));
        }

        // Firestore generates its own ID upon adding a document.
        // If we need to use the ID from $trigger->getId(), we'd use ->document($trigger->getId())->set($doc)
        // For now, let Firestore generate the ID.
        $documentReference = $this->collectionReference->document("triggers")->collection("triggers")->add($doc);
        
        // If the trigger ID was null and is now set by Firestore, update the trigger object.
        // This might be problematic if $trigger is passed by value.
        // However, the current method signature implies we return Firestore's generated ID.
        // If $trigger->setId() was meant to update the object passed in, PHP's object handling
        // (passed by reference by default for objects) should allow it if it were called here.
        // For now, we just return the new ID from Firestore.
        return $documentReference->id();
    }

    public function deleteTriggerById(string $id): void
    {
        $this->collectionReference->document("triggers")->collection("triggers")->document($id)->delete();
    }
}
