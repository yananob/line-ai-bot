<?php declare(strict_types=1);

namespace App\Infrastructure\Persistence\Firestore;

use App\Domain\Config\ConfigRepository;
use Google\Cloud\Firestore\FirestoreClient;
use Google\Cloud\Firestore\DocumentReference;
use Google\Cloud\Firestore\CollectionReference;

class FirestoreConfigRepository extends AbstractFirestoreRepository implements ConfigRepository
{
    private DocumentReference $documentRoot;

    public function __construct(bool $isTest = true, ?FirestoreClient $db = null)
    {
        parent::__construct($isTest, $db);
        $this->documentRoot = $this->db->collection($this->collectionName)->document('configs');
    }

    private function getBotCollection(string $botId): CollectionReference
    {
        return $this->documentRoot->collection($botId);
    }

    public function findAllBotIds(): array
    {
        $botIdCollections = $this->documentRoot->collections();
        $botIds = [];
        foreach ($botIdCollections as $botCollection) {
            $botIds[] = $botCollection->id();
        }
        return $botIds;
    }

    public function findBotConfig(string $botId): ?array
    {
        $snapshot = $this->getBotCollection($botId)->document('config')->snapshot();
        return $snapshot->exists() ? $snapshot->data() : null;
    }

    public function saveBotConfig(string $botId, array $data): void
    {
        $this->getBotCollection($botId)->document('config')->set($data);
    }

    public function findTriggers(string $botId): array
    {
        $triggersCollection = $this->getBotCollection($botId)->document('triggers')->collection('triggers');
        $documents = $triggersCollection->documents();
        $triggers = [];
        foreach ($documents as $doc) {
            if ($doc->exists()) {
                $triggers[$doc->id()] = $doc->data();
            }
        }
        return $triggers;
    }

    public function saveTrigger(string $botId, string $triggerId, array $data): void
    {
        $triggersCollection = $this->getBotCollection($botId)->document('triggers')->collection('triggers');
        $triggersCollection->document($triggerId)->set($data);
    }

    public function deleteTrigger(string $botId, string $triggerId): void
    {
        $triggersCollection = $this->getBotCollection($botId)->document('triggers')->collection('triggers');
        $triggersCollection->document($triggerId)->delete();
    }

    public function deleteBot(string $botId): void
    {
        // Recursively deleting in Firestore is complex if not using CLI or a specific library.
        // For simplicity, we at least delete 'config' document.
        // Triggers might need to be deleted one by one or by deleting the collection.
        $this->getBotCollection($botId)->document('config')->delete();

        $triggers = $this->findTriggers($botId);
        foreach ($triggers as $id => $data) {
            $this->deleteTrigger($botId, $id);
        }
        $this->getBotCollection($botId)->document('triggers')->delete();
    }
}
