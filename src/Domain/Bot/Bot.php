<?php declare(strict_types=1);

namespace MyApp\Domain\Bot;

use MyApp\BotConfig;
use MyApp\Domain\Bot\Trigger\Trigger;

class Bot
{
    private string $id;
    private array $botCharacteristics = [];
    private array $humanCharacteristics = [];
    private array $configRequests = [];
    private string $lineTarget = '';
    private array $triggers = []; // This will hold Trigger objects
    private ?BotConfig $configDefault;

    public function __construct(string $id, ?BotConfig $configDefault = null)
    {
        $this->id = $id;
        $this->configDefault = $configDefault;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getBotCharacteristics(): array
    {
        if (empty($this->botCharacteristics) && $this->configDefault !== null) {
            return $this->configDefault->getBotCharacteristics();
        }
        return $this->botCharacteristics;
    }

    public function getHumanCharacteristics(): array
    {
        if (empty($this->humanCharacteristics) && $this->configDefault !== null) {
            return $this->configDefault->getHumanCharacteristics();
        }
        return $this->humanCharacteristics;
    }

    public function hasHumanCharacteristics(): bool
    {
        return !empty($this->humanCharacteristics) || ($this->configDefault !== null && $this->configDefault->hasHumanCharacteristics());
    }

    public function getConfigRequests(bool $usePersonal = true, bool $useDefault = true): array
    {
        $requests = [];
        if ($usePersonal) {
            $requests = $this->configRequests;
        }

        if ($useDefault && $this->configDefault !== null) {
            // Assuming BotConfig::getConfigRequests(true, false) returns only personal configRequests
            $defaultRequests = $this->configDefault->getConfigRequests(true, false);
            // Simple merge, could be more sophisticated depending on desired behavior for duplicates
            $requests = array_merge($defaultRequests, $requests);
        }
        return $requests;
    }

    public function getLineTarget(): string
    {
        if (empty($this->lineTarget) && $this->configDefault !== null) {
            return $this->configDefault->getLineTarget();
        }
        return $this->lineTarget;
    }

    public function getTriggers(): array
    {
        return $this->triggers;
    }

    public function addTrigger(Trigger $trigger): string
    {
        $triggerId = uniqid('trigger_', true);
        $trigger->setId($triggerId);
        $this->triggers[$triggerId] = $trigger;
        return $triggerId;
    }

    public function deleteTriggerById(string $id): void
    {
        // Ensure that we only attempt to unset if the trigger exists.
        // And if triggers are objects, we might want to compare $trigger->getId()
        // However, since we store them by $triggerId as key, direct unsetting is fine.
        if (isset($this->triggers[$id])) {
            unset($this->triggers[$id]);
        }
    }

    // Placeholder methods for Repository
    public function setBotCharacteristics(array $characteristics): void
    {
        $this->botCharacteristics = $characteristics;
    }

    public function setHumanCharacteristics(array $characteristics): void
    {
        $this->humanCharacteristics = $characteristics;
    }

    public function setConfigRequests(array $requests): void
    {
        $this->configRequests = $requests;
    }

    public function setLineTarget(string $target): void
    {
        $this->lineTarget = $target;
    }

    public function setTriggers(array $triggers): void
    {
        $this->triggers = $triggers;
    }
}
