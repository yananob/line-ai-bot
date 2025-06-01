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
use yananob\MyTools\Logger;

class FirestoreBotRepository implements BotRepository
{
    private FirestoreClient $db;
    private string $collectionName;
    private DocumentReference $documentRoot; // e.g., /ai-bots/{bot_id}/configs/

    public function __construct(bool $isTest = true)
    {
        $this->collectionName = $isTest ? "ai-bot-test" : "ai-bot";
        $this->db = new FirestoreClient(["keyFile" => json_decode(getenv("FIREBASE_CONFIG"), true)]);
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
        $logger = new Logger("FirestoreBotRepository::loadBotFromSnapshot");
        $logger->log("Entry. Bot ID: {$botId}");

        if (!$configSnapshot->exists()) {
            $logger->log("Config snapshot does not exist for Bot ID: {$botId}. Exiting.");
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
            $logger->log("Processing trigger doc ID: {$triggerDoc->id()}. Raw data: " . json_encode($tData));
            // Assuming TimerTrigger for now, this would need to be more flexible
            if (isset($tData['event']) && $tData['event'] === 'timer') {
                $dateForTrigger = $tData['date'] ?? null;
                $timeForTrigger = $tData['time'] ?? null;

                $logger->log("DEBUG TimerTrigger Instantiation: Input Date='{$dateForTrigger}', Input Time='{$timeForTrigger}'");
                $logger->log("DEBUG TimerTrigger Instantiation: Runtime Consts::TIMEZONE = " . \MyApp\Consts::TIMEZONE);

                $trigger = new TimerTrigger($dateForTrigger, $timeForTrigger, $tData['request']);
                $trigger->setId($triggerDoc->id()); // Use Firestore document ID as trigger ID
                $triggers[$trigger->getId()] = $trigger;
            }
        }
        $bot->setTriggers($triggers);

        $logger->log("Exit. Bot ID: {$botId}");
        return $bot;
    }

    public function findById(string $id): ?Bot
    {
        $logger = new Logger("FirestoreBotRepository::findById");
        $logger->log("Entry. ID: {$id}");

        if ($id === 'default') { // Default bot should be fetched by findDefault
            $logger->log("ID is 'default', forwarding to findDefault(). ID: {$id}");
            return $this->findDefault();
        }

        $botCollection = $this->getBotCollection($id);
        $configSnapshot = $botCollection->document('config')->snapshot();

        if (!$configSnapshot->exists()) {
            $logger->log("Config snapshot not found for ID: {$id}. Exiting.");
            return null;
        }
        
        // All bots (except default itself) need the default config for fallback
        $defaultBotConfig = $this->findDefault(); // This will have its own logging

        $resultBot = $this->loadBotFromSnapshot($id, $configSnapshot, $defaultBotConfig);
        $logger->log("Exit. ID: {$id}");
        return $resultBot;
    }

    public function findDefault(): ?Bot
    {
        $logger = new Logger("FirestoreBotRepository::findDefault");
        $logger->log("Entry.");

        $defaultBotCollection = $this->getBotCollection('default');
        $configSnapshot = $defaultBotCollection->document('config')->snapshot();

        if (!$configSnapshot->exists()) {
            // This case should ideally not happen in a well-configured system
            // or means the default bot config is missing.
            $logger->log("Default config snapshot not found. Exiting.");
            return null;
        }

        // The default bot does not have a further default config, so pass null.
        $resultBot = $this->loadBotFromSnapshot('default', $configSnapshot, null);
        $logger->log("Exit.");
        return $resultBot;
    }

    public function save(Bot $bot): void
    {
        $logger = new Logger("FirestoreBotRepository::save");
        $logger->log("Entry. Bot ID: {$bot->getId()}");

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
        $logger->log("Exit. Bot ID: {$bot->getId()}");
    }

    public function getAllUserBots(): array
    {
        $logger = new Logger("FirestoreBotRepository::getAllUserBots");
        $logger->log("Entry.");

        $userBots = [];
        // List collections within the 'configs' document. Each subcollection ID is a bot ID.
        $botIdCollections = $this->documentRoot->collections();
        
        foreach ($botIdCollections as $botCollection) {
            $botId = $botCollection->id();
            if ($botId !== 'default') {
                // findById will fetch the bot, including its default config.
                $bot = $this->findById($botId); // This will have its own logging
                if ($bot) {
                    $logger->log("Found user bot: {$botId}");
                    $userBots[] = $bot;
                }
            }
        }
        $logger->log("Exit. Found " . count($userBots) . " user bots.");
        return $userBots;
    }
}
