<?php declare(strict_types=1);

namespace App\Domain\Bot;

use App\Domain\Bot\Trigger\Trigger;
use App\Domain\Bot\ValueObject\StringList;
use App\Domain\Exception\TriggerNotFoundException;
use App\Domain\Bot\ValueObject\BotPersonalityConfig;

class Bot
{
    private string $id;
    private string $name = '';
    private BotPersonalityConfig $personality;
    private StringList $configRequests;
    private string $lineTarget = '';
    private array $triggers = []; // This will hold Trigger objects
    private ?Bot $defaultBot;

    public function __construct(string $id, ?Bot $defaultBot = null)
    {
        $this->id = $id;
        $this->defaultBot = $defaultBot;
        $this->personality = new BotPersonalityConfig(new StringList([]), new StringList([]));
        $this->configRequests = new StringList([]);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getPersonality(): BotPersonalityConfig
    {
        return $this->personality;
    }

    public function getBotCharacteristics(): StringList
    {
        $chars = $this->personality->getBotCharacteristics();
        if ($this->defaultBot !== null) {
            $defaultChars = $this->defaultBot->getBotCharacteristics();
            $chars = $defaultChars->merge($chars);
        }
        return $chars;
    }

    public function getHumanCharacteristics(): StringList
    {
        $chars = $this->personality->getHumanCharacteristics();
        if ($this->defaultBot !== null) {
            $defaultChars = $this->defaultBot->getHumanCharacteristics();
            $chars = $defaultChars->merge($chars);
        }
        return $chars;
    }

    public function hasHumanCharacteristics(): bool
    {
        if (!$this->personality->getHumanCharacteristics()->isEmpty()) {
            return true;
        }
        return $this->defaultBot !== null && $this->defaultBot->hasHumanCharacteristics();
    }

    public function getConfigRequests(bool $usePersonal = true, bool $useDefault = true): StringList
    {
        $requests = new StringList([]);
        if ($usePersonal) {
            $requests = $this->configRequests;
        }

        if ($useDefault && $this->defaultBot !== null) {
            $defaultRequests = $this->defaultBot->getConfigRequests(true, false);
            // Default requests come first
            $requests = $defaultRequests->merge($requests);
        }
        return $requests;
    }

    public function getLineTarget(): string
    {
        if (empty($this->lineTarget) && $this->defaultBot !== null) {
            return $this->defaultBot->getLineTarget();
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
        if (!isset($this->triggers[$id])) {
            throw new TriggerNotFoundException("Trigger with ID '{$id}' not found.");
        }
        unset($this->triggers[$id]);
    }

    public function setPersonality(BotPersonalityConfig $personality): void
    {
        $this->personality = $personality;
    }

    public function setBotCharacteristics(array $characteristics): void
    {
        $this->personality = new BotPersonalityConfig(
            new StringList($characteristics),
            $this->personality->getHumanCharacteristics()
        );
    }

    public function setHumanCharacteristics(array $characteristics): void
    {
        $this->personality = new BotPersonalityConfig(
            $this->personality->getBotCharacteristics(),
            new StringList($characteristics)
        );
    }

    public function setConfigRequests(array $requests): void
    {
        $this->configRequests = new StringList($requests);
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
