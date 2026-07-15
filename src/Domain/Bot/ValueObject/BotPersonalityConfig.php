<?php declare(strict_types=1);

namespace App\Domain\Bot\ValueObject;

class BotPersonalityConfig
{
    public function __construct(
        private StringList $botCharacteristics,
        private StringList $humanCharacteristics,
        private StringList $configRequests = new StringList([])
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

    public function getConfigRequests(): StringList
    {
        return $this->configRequests;
    }

    public function isEmpty(): bool
    {
        return $this->botCharacteristics->isEmpty() &&
               $this->humanCharacteristics->isEmpty() &&
               $this->configRequests->isEmpty();
    }

    public function merge(BotPersonalityConfig $default): self
    {
        return new self(
            $default->getBotCharacteristics()->merge($this->botCharacteristics),
            $default->getHumanCharacteristics()->merge($this->humanCharacteristics),
            $default->getConfigRequests()->merge($this->configRequests)
        );
    }
}
