<?php declare(strict_types=1);

namespace App\Infrastructure\Persistence\Firestore;

use Google\Cloud\Firestore\FirestoreClient;
use Google\Cloud\Firestore\DocumentReference;
use Google\Cloud\Firestore\CollectionReference;
use Google\Cloud\Firestore\DocumentSnapshot;
use App\Domain\Bot\Bot;
use App\Domain\Bot\BotRepository;
use App\Domain\Bot\Trigger\TimerTrigger;
use App\Domain\Bot\Trigger\Trigger;
use App\Domain\Exception\BotNotFoundException;

class FirestoreBotRepository extends AbstractFirestoreRepository implements BotRepository
{
    private DocumentReference $documentRoot; // e.g., /ai-bots/{bot_id}/configs/

    public function __construct(?FirestoreClient $db = null)
    {
        parent::__construct($db);
        // This documentRoot points to the 'configs' document within the main collection.
        // e.g. /ai-bot/configs or /ai-bot-test/configs
        // Individual bot data will be subcollections under this.
        $this->documentRoot = $this->db->collection($this->collectionName)->document('configs');
    }

    private function getBotCollection(string $botId): CollectionReference
    {
        return $this->documentRoot->collection($botId);
    }

    private function loadBotFromSnapshot(string $botId, DocumentSnapshot $configSnapshot, ?Bot $defaultBotConfig): ?Bot
    {
        if (!$configSnapshot->exists()) {
            return null;
        }

        $bot = new Bot($botId, $defaultBotConfig);
        $data = $configSnapshot->data();

        $bot->setBotCharacteristics($data['bot_characteristics'] ?? []);
        $bot->setHumanCharacteristics($data['human_characteristics'] ?? []);
        $bot->setConfigRequests($data['requests'] ?? []);
        $bot->setLineTarget($data['line_target'] ?? '');

        // Load triggers
        $triggersSnapshot = $this->getBotCollection($botId)->document('triggers')->snapshot();
        $triggers = [];
        if ($triggersSnapshot->exists()) {
            $tDataList = $triggersSnapshot->data()['triggers'] ?? [];
            foreach ($tDataList as $id => $tData) {
                // Assuming TimerTrigger for now, this would need to be more flexible
                if (isset($tData['event']) && $tData['event'] === 'timer') {
                    $dateForTrigger = (string)($tData['date'] ?? '');
                    $timeForTrigger = (string)($tData['time'] ?? '');
                    $request = (string)($tData['request'] ?? '');
                    $trigger = new TimerTrigger($dateForTrigger, $timeForTrigger, $request);
                    $trigger->setId((string)$id);
                    $triggers[$trigger->getId()] = $trigger;
                }
            }
        }
        $bot->setTriggers($triggers);

        return $bot;
    }

    public function findById(string $id): Bot
    {
        if ($id === 'default') { // Default bot should be fetched by findDefault
            error_log("Warning: Attempted to find default bot using findById. Use findDefault() instead.");
            return $this->findDefault();
        }

        $botCollection = $this->getBotCollection($id);
        $configSnapshot = $botCollection->document('config')->snapshot();

        if (!$configSnapshot->exists()) {
            throw new BotNotFoundException("Bot with ID '{$id}' not found.");
        }

        // 各Botは、自身のconfig + defaultで動作する
        $defaultBotConfig = $this->findDefault();
        error_log("Loading bot with ID '{$id}' using default config.");

        return $this->loadBotFromSnapshot($id, $configSnapshot, $defaultBotConfig);
    }

    public function findOrDefault(string $id): Bot
    {
        try {
            return $this->findById($id);
        } catch (BotNotFoundException $e) {
            $defaultBotConfig = $this->findDefault();
            return new Bot($id, $defaultBotConfig);
        }
    }

    public function findDefault(): Bot
    {
        $defaultBotCollection = $this->getBotCollection('default');
        $configSnapshot = $defaultBotCollection->document('config')->snapshot();

        if (!$configSnapshot->exists()) {
            throw new BotNotFoundException("Default bot configuration with ID 'default' not found.");
        }

        // The default bot does not have a further default config, so pass null.
        return $this->loadBotFromSnapshot('default', $configSnapshot, null);
    }

    public function save(Bot $bot): void
    {
        $botCollection = $this->getBotCollection($bot->getId());

        // Save main config (only personal settings, not default ones)
        $configData = [
            'bot_characteristics' => $bot->getPersonality()->getBotCharacteristics()->toArray(),
            'human_characteristics' => $bot->getPersonality()->getHumanCharacteristics()->toArray(),
            'requests' => $bot->getConfigRequests(true, false)->toArray(), // Only personal requests
            'line_target' => $bot->getLineTarget(),
        ];
        $botCollection->document('config')->set($configData);

        // Save triggers
        $triggerDataList = [];
        foreach ($bot->getTriggers() as $trigger) {
            $triggerDataList[$trigger->getId() ?: uniqid('trigger_')] = $trigger->toArray();
        }
        $botCollection->document('triggers')->set(['triggers' => $triggerDataList]);
    }

    public function getAllUserBots(): array
    {
        $userBots = [];
        $botIdCollections = $this->documentRoot->collections();
        
        foreach ($botIdCollections as $botCollection) {
            $botId = $botCollection->id();
            if ($botId !== 'default') {
                try {
                    $userBots[] = $this->findById($botId);
                } catch (BotNotFoundException $e) {
                    // This case might not happen if the collection exists,
                    // but it's safer to catch it.
                    error_log("Bot with ID '{$botId}' collection exists but config document is missing.");
                }
            }
        }
        return $userBots;
    }
}
