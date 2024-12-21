<?php

declare(strict_types=1);

namespace MyApp;

class BotConfig
{

    public function __construct() {}

    public function getBotCharacteristics(): array
    {
        // TODO: default + generatedでの内容を返す
        return [];
    }
    public function getHumanCharacteristics(): array {}

    public function hasHumanCharacteristics(): bool
    {
        return (!empty($this->getHumanCharacteristics()));
    }

    public function isChatMode(): bool
    {
        return $this->mode === Mode::Chat->value;
    }

    public function isConsultingMode(): bool
    {
        return $this->mode === Mode::Consulting->value;
    }

    public function getLineTarget(): string {}
}
