<?php declare(strict_types=1);

namespace App\Infrastructure\Persistence\Firestore;

use App\Domain\Config\ConfigRepository;
use Google\Cloud\Firestore\FirestoreClient;
use Google\Cloud\Firestore\DocumentReference;
use Google\Cloud\Firestore\CollectionReference;

class FirestoreConfigRepository extends AbstractFirestoreRepository implements ConfigRepository
{
    private DocumentReference $documentRoot;

    public function __construct(?FirestoreClient $db = null)
    {
        parent::__construct($db);
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
        $triggers = [];
        $documents = $this->getBotCollection($botId)->document('triggers')->collection('triggers')->documents();
        foreach ($documents as $doc) {
            $triggers[$doc->id()] = $doc->data();
        }
        return $triggers;
    }

    public function findTrigger(string $botId, string $triggerId): ?array
    {
        $snapshot = $this->getBotCollection($botId)->document('triggers')->collection('triggers')->document($triggerId)->snapshot();
        return $snapshot->exists() ? $snapshot->data() : null;
    }

    public function saveTrigger(string $botId, string $triggerId, array $data): void
    {
        $this->getBotCollection($botId)->document('triggers')->collection('triggers')->document($triggerId)->set($data);
    }

    public function deleteTrigger(string $botId, string $triggerId): void
    {
        $this->getBotCollection($botId)->document('triggers')->collection('triggers')->document($triggerId)->delete();
    }

    public function deleteBot(string $botId): void
    {
        $this->getBotCollection($botId)->document('config')->delete();

        // Delete all triggers in the sub-collection
        $triggerDocs = $this->getBotCollection($botId)->document('triggers')->collection('triggers')->documents();
        foreach ($triggerDocs as $doc) {
            $doc->reference()->delete();
        }
        $this->getBotCollection($botId)->document('triggers')->delete();
    }
}
