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
        $snapshot = $this->getBotCollection($botId)->document('triggers')->snapshot();
        if ($snapshot->exists()) {
            return $snapshot->data()['triggers'] ?? [];
        }
        return [];
    }

    public function saveTrigger(string $botId, string $triggerId, array $data): void
    {
        $docRef = $this->getBotCollection($botId)->document('triggers');
        $snapshot = $docRef->snapshot();
        $triggers = $snapshot->exists() ? ($snapshot->data()['triggers'] ?? []) : [];
        $triggers[$triggerId] = $data;
        $docRef->set(['triggers' => $triggers]);
    }

    public function deleteTrigger(string $botId, string $triggerId): void
    {
        $docRef = $this->getBotCollection($botId)->document('triggers');
        $snapshot = $docRef->snapshot();
        if ($snapshot->exists()) {
            $triggers = $snapshot->data()['triggers'] ?? [];
            if (isset($triggers[$triggerId])) {
                unset($triggers[$triggerId]);
                $docRef->set(['triggers' => $triggers]);
            }
        }
    }

    public function deleteBot(string $botId): void
    {
        $this->getBotCollection($botId)->document('config')->delete();
        $this->getBotCollection($botId)->document('triggers')->delete();
    }
}
