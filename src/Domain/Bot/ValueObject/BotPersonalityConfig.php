<?php declare(strict_types=1);

namespace App\Domain\Bot\ValueObject;

class BotPersonalityConfig
{
    public function __construct(
        private StringList $botCharacteristics,
        private StringList $humanCharacteristics
    ) {
    }

    public function getBotCharacteristics(): StringList
    {
        return $this->botCharacteristics;
    }

    public function getHumanCharacteristics(): StringList
    {
        return $this->humanCharacteristics;
    }

    public function isEmpty(): bool
    {
        return $this->botCharacteristics->isEmpty() && $this->humanCharacteristics->isEmpty();
    }
}
