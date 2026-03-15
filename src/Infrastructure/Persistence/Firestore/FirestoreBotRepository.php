<?php declare(strict_types=1);

namespace MyApp\Infrastructure\Persistence\Firestore;

use Google\Cloud\Firestore\FirestoreClient;
use Google\Cloud\Firestore\DocumentReference;
use Google\Cloud\Firestore\CollectionReference;
use Google\Cloud\Firestore\DocumentSnapshot;
use MyApp\Domain\Bot\Bot;
use MyApp\Domain\Bot\BotRepository;
use MyApp\Domain\Bot\Trigger\TimerTrigger;
use MyApp\Domain\Bot\Trigger\Trigger;
use MyApp\Domain\Exception\BotNotFoundException;

class FirestoreBotRepository implements BotRepository
{
    private FirestoreClient $db;
    private string $collectionName;
    private DocumentReference $documentRoot; // e.g., /ai-bots/{bot_id}/configs/

    public function __construct(bool $isTest = true, ?FirestoreClient $db = null)
    {
        $this->collectionName = $isTest ? "ai-bot-test" : "ai-bot";
        $this->db = $db ?? new FirestoreClient(["keyFile" => json_decode(getenv("FIREBASE_SERVICE_ACCOUNT") ?: '[]', true)]);
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
        $triggersCollection = $this->getBotCollection($botId)->document('triggers')->collection('triggers');
        $triggerDocuments = $triggersCollection->documents();
        $triggers = [];
        foreach ($triggerDocuments as $triggerDoc) {
            if (!$triggerDoc->exists()) continue;
            $tData = $triggerDoc->data();
            // Assuming TimerTrigger for now, this would need to be more flexible
            if (isset($tData['event']) && $tData['event'] === 'timer') {
                $dateForTrigger = (string)($tData['date'] ?? '');
                $timeForTrigger = (string)($tData['time'] ?? '');
                $request = (string)($tData['request'] ?? '');
                $trigger = new TimerTrigger($dateForTrigger, $timeForTrigger, $request);
                $trigger->setId($triggerDoc->id()); // Use Firestore document ID as trigger ID
                $triggers[$trigger->getId()] = $trigger;
            }
        }
        $bot->setTriggers($triggers);

        return $bot;
    }

    public function findById(string $id): ?Bot
    {
        if ($id === 'default') { // Default bot should be fetched by findDefault
            error_log("Warning: Attempted to find default bot using findById. Use findDefault() instead.");
            return $this->findDefault();
        }

        $botCollection = $this->getBotCollection($id);
        $configSnapshot = $botCollection->document('config')->snapshot();

        if (!$configSnapshot->exists()) {
            error_log("Bot with ID '{$id}' not found.");
            return null;
        }

        // 各Botは、自身のconfig + defaultで動作する
        $defaultBotConfig = $this->findDefault();
        error_log("Loading bot with ID '{$id}' using default config.");

        return $this->loadBotFromSnapshot($id, $configSnapshot, $defaultBotConfig);
    }

    public function findOrDefault(string $id): Bot
    {
        $bot = $this->findById($id);
        if ($bot !== null) {
            return $bot;
        }

        $defaultBotConfig = $this->findDefault();
        return new Bot($id, $defaultBotConfig);
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

        // Save main config
        $configData = [
            'bot_characteristics' => $bot->getBotCharacteristics()->toArray(),
            'human_characteristics' => $bot->getHumanCharacteristics()->toArray(),
            'requests' => $bot->getConfigRequests(true, false)->toArray(), // Only personal requests
            'line_target' => $bot->getLineTarget(),
        ];
        $botCollection->document('config')->set($configData);

        // Save triggers
        $triggersSubCollection = $botCollection->document('triggers')->collection('triggers');
        
        // Simple strategy: delete existing triggers and re-add.
        $existingTriggers = $triggersSubCollection->documents();
        foreach ($existingTriggers as $doc) {
            $doc->reference()->delete();
        }

        foreach ($bot->getTriggers() as $trigger) {
            $triggerData = $trigger->toArray();
            if ($trigger->getId()) {
                $triggersSubCollection->document($trigger->getId())->set($triggerData);
            } else {
                $newDocRef = $triggersSubCollection->add($triggerData);
                $trigger->setId($newDocRef->id());
            }
        }
    }

    public function getAllUserBots(): array
    {
        $userBots = [];
        $botIdCollections = $this->documentRoot->collections();
        
        foreach ($botIdCollections as $botCollection) {
            $botId = $botCollection->id();
            if ($botId !== 'default') {
                $bot = $this->findById($botId);
                if ($bot !== null) {
                    $userBots[] = $bot;
                }
            }
        }
        return $userBots;
    }
}
