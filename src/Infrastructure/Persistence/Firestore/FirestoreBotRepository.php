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

class FirestoreBotRepository implements BotRepository
{
    private FirestoreClient $db;
    private string $collectionName;
    private DocumentReference $documentRoot; // e.g., /ai-bots/{bot_id}/configs/

    public function __construct(bool $isTest = true)
    {
        $this->collectionName = $isTest ? "ai-bot-test" : "ai-bot";
        $this->db = new FirestoreClient([
            // TODO: allow project id to be configurable for tests, maybe via env var
            // 'projectId' => 'your-project-id',
        ]);
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
                $trigger = new TimerTrigger($tData['date'], $tData['time'], $tData['request']);
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
            return $this->findDefault();
        }

        $botCollection = $this->getBotCollection($id);
        $configSnapshot = $botCollection->document('config')->snapshot();

        if (!$configSnapshot->exists()) {
            return null;
        }
        
        // All bots (except default itself) need the default config for fallback
        $defaultBotConfig = $this->findDefault();

        return $this->loadBotFromSnapshot($id, $configSnapshot, $defaultBotConfig);
    }

    public function findDefault(): ?Bot
    {
        $defaultBotCollection = $this->getBotCollection('default');
        $configSnapshot = $defaultBotCollection->document('config')->snapshot();

        if (!$configSnapshot->exists()) {
            // This case should ideally not happen in a well-configured system
            // or means the default bot config is missing.
            return null;
        }

        // The default bot does not have a further default config, so pass null.
        return $this->loadBotFromSnapshot('default', $configSnapshot, null);
    }

    public function save(Bot $bot): void
    {
        $botCollection = $this->getBotCollection($bot->getId());

        // Save main config
        $configData = [
            'bot_characteristics' => $bot->getBotCharacteristics(),
            'human_characteristics' => $bot->getHumanCharacteristics(),
            'requests' => $bot->getConfigRequests(true, false), // Only personal requests
            'line_target' => $bot->getLineTarget(),
        ];
        $botCollection->document('config')->set($configData);

        // Save triggers
        $triggersSubCollection = $botCollection->document('triggers')->collection('triggers');
        
        // Simple strategy: delete existing triggers and re-add.
        // A more sophisticated approach might involve checking existing ones.
        $existingTriggers = $triggersSubCollection->documents();
        foreach ($existingTriggers as $doc) {
            $doc->reference()->delete();
        }

        foreach ($bot->getTriggers() as $trigger) {
            $triggerData = $trigger->toArray();
            // The ID for the document comes from $trigger->getId(), which should be set.
            // If $trigger->getId() is null, Firestore will generate an ID.
            // Bot::addTrigger is expected to generate an ID if one isn't present.
            if ($trigger->getId()) {
                $triggersSubCollection->document($trigger->getId())->set($triggerData);
            } else {
                // This case should ideally not happen if Bot::addTrigger ensures an ID.
                // If it can, we might need to update the trigger object with the new ID.
                $newDocRef = $triggersSubCollection->add($triggerData);
                $trigger->setId($newDocRef->id());
            }
        }
    }

    public function getAllUserBots(): array
    {
        $userBots = [];
        // List collections within the 'configs' document. Each subcollection ID is a bot ID.
        $botIdCollections = $this->documentRoot->collections();
        
        foreach ($botIdCollections as $botCollection) {
            $botId = $botCollection->id();
            if ($botId !== 'default') {
                // findById will fetch the bot, including its default config.
                $bot = $this->findById($botId);
                if ($bot) {
                    $userBots[] = $bot;
                }
            }
        }
        return $userBots;
    }
}
