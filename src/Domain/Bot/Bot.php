<?php declare(strict_types=1);

namespace App\Domain\Bot;

use App\Domain\Bot\Trigger\Trigger;
use App\Domain\Bot\ValueObject\StringList;
use App\Domain\Exception\TriggerNotFoundException;
use App\Domain\Bot\ValueObject\BotPersonalityConfig;
use App\Domain\Bot\Event\DomainEvent;
use App\Domain\Bot\Event\BotCreatedEvent;
use App\Domain\Bot\Event\BotConfigChangedEvent;
use App\Domain\Bot\Event\TriggerAddedToBotEvent;
use App\Domain\Bot\Event\TriggerRemovedFromBotEvent;

/**
 * ボットの基本情報を表すアグリゲート。
 */
class Bot
{
    private string $id;
    private string $name;
    private BotPersonalityConfig $personality;
    private string $lineTarget;
    private array $triggers; // This will hold Trigger objects
    private ?Bot $defaultBot;

    /** @var DomainEvent[] */
    private array $recordedEvents = [];

    /**
     * @param string $id
     * @param string $name
     * @param BotPersonalityConfig|null $personality
     * @param string $lineTarget
     * @param array<string, Trigger> $triggers
     * @param Bot|null $defaultBot
     */
    public function __construct(
        string $id,
        string $name = '',
        ?BotPersonalityConfig $personality = null,
        string $lineTarget = '',
        array $triggers = [],
        ?Bot $defaultBot = null
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->personality = $personality ?? new BotPersonalityConfig(new StringList([]), new StringList([]));
        $this->lineTarget = $lineTarget;
        $this->triggers = $triggers;
        $this->defaultBot = $defaultBot;
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
        $this->recordEvent(new BotConfigChangedEvent($this->id, $this->name, $this->personality));
    }

    public function getPersonality(): BotPersonalityConfig
    {
        return $this->personality;
    }

    public function getMergedPersonality(): BotPersonalityConfig
    {
        if ($this->defaultBot !== null) {
            return $this->personality->merge($this->defaultBot->getPersonality());
        }
        return $this->personality;
    }

    public function getBotCharacteristics(): StringList
    {
        return $this->getMergedPersonality()->getBotCharacteristics();
    }

    public function getHumanCharacteristics(): StringList
    {
        return $this->getMergedPersonality()->getHumanCharacteristics();
    }

    public function hasHumanCharacteristics(): bool
    {
        return !$this->getHumanCharacteristics()->isEmpty();
    }

    public function getConfigRequests(bool $usePersonal = true, bool $useDefault = true): StringList
    {
        if ($usePersonal && $useDefault) {
            return $this->getMergedPersonality()->getConfigRequests();
        }

        if ($usePersonal) {
            return $this->personality->getConfigRequests();
        }

        if ($useDefault && $this->defaultBot !== null) {
            return $this->defaultBot->getPersonality()->getConfigRequests();
        }

        return new StringList([]);
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

    public function getTriggerById(string $id): ?Trigger
    {
        return $this->triggers[$id] ?? null;
    }

    public function addTrigger(Trigger $trigger): string
    {
        $triggerId = uniqid('trigger_', true);
        $trigger->setId($triggerId);
        $this->triggers[$triggerId] = $trigger;
        $this->recordEvent(new TriggerAddedToBotEvent($this->id, $trigger));
        return $triggerId;
    }

    public function deleteTriggerById(string $id): void
    {
        if (!isset($this->triggers[$id])) {
            throw new TriggerNotFoundException("Trigger with ID '{$id}' not found.");
        }
        unset($this->triggers[$id]);
        $this->recordEvent(new TriggerRemovedFromBotEvent($this->id, $id));
    }

    public function setPersonality(BotPersonalityConfig $personality): void
    {
        $this->personality = $personality;
        $this->recordEvent(new BotConfigChangedEvent($this->id, $this->name, $this->personality));
    }

    public function setConfigRequests(StringList $configRequests): void
    {
        // For compatibility with previous API, update personality's configRequests
        $this->personality = new BotPersonalityConfig(
            $this->personality->getBotCharacteristics(),
            $this->personality->getHumanCharacteristics(),
            $configRequests
        );
        $this->recordEvent(new BotConfigChangedEvent($this->id, $this->name, $this->personality));
    }

    public function setLineTarget(string $target): void
    {
        $this->lineTarget = $target;
        $this->recordEvent(new BotConfigChangedEvent($this->id, $this->name, $this->personality));
    }

    public function setTriggers(array $triggers): void
    {
        $this->triggers = $triggers;
    }

    public function setTrigger(string $id, Trigger $trigger): void
    {
        $trigger->setId($id);
        $this->triggers[$id] = $trigger;
    }

    /**
     * ドメインイベントを記録します。
     */
    public function recordEvent(DomainEvent $event): void
    {
        $this->recordedEvents[] = $event;
    }

    /**
     * 記録されたドメインイベントを取得し、クリアします。
     *
     * @return DomainEvent[]
     */
    public function releaseEvents(): array
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];
        return $events;
    }
}
