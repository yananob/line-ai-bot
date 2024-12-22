<?php

declare(strict_types=1);

namespace MyApp;

use Google\Cloud\Firestore\CollectionReference;
use Google\Cloud\Firestore\DocumentReference;
use Google\Cloud\Firestore\DocumentSnapshot;

class BotConfig
{
    private DocumentSnapshot $configGenerated;
    // private DocumentReference $triggersGenerated;
    private string $mode;

    public function __construct(CollectionReference $collectionReference, private ?BotConfig $configDefault)
    {
        $this->configGenerated = $collectionReference->document("config-generated")->snapshot()->data();
        $this->mode = $this->configGenerated["mode"];
    }

    private function __getConfig(string $fieldName, bool $useDefaultAndGenerated): array
    {
        // $result = [];
        $result = $this->configGenerated[$fieldName];
        if ((empty($generated) || $useDefaultAndGenerated) && !empty($configDefault)) {
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

    public function isChatMode(): bool
    {
        return $this->mode === Mode::Chat->value;
    }

    public function isConsultingMode(): bool
    {
        return $this->mode === Mode::Consulting->value;
    }

    public function getLineTarget(): string
    {
        return $this->configDefault["line_target"];
    }
}
