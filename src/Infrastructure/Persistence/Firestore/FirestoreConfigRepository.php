<?php declare(strict_types=1);

namespace App\Infrastructure\Persistence\Firestore;

use App\Domain\Config\Config;
use App\Domain\Config\ConfigRepository;
use Google\Cloud\Firestore\FirestoreClient;

class FirestoreConfigRepository implements ConfigRepository
{
    private FirestoreClient $db;
    private string $collectionName;

    public function __construct(bool $isTest = true, ?FirestoreClient $db = null)
    {
        // Use 'config' as specified in the issue.
        $this->collectionName = $isTest ? "config-test" : "config";
        $this->db = $db ?? new FirestoreClient(["keyFile" => json_decode(getenv("FIREBASE_SERVICE_ACCOUNT") ?: '[]', true)]);
    }

    public function findAll(): array
    {
        $documents = $this->db->collection($this->collectionName)->documents();
        $configs = [];
        foreach ($documents as $doc) {
            if ($doc->exists()) {
                $configs[] = new Config($doc->id(), $doc->data());
            }
        }
        return $configs;
    }

    public function findById(string $id): ?Config
    {
        $snapshot = $this->db->collection($this->collectionName)->document($id)->snapshot();
        if (!$snapshot->exists()) {
            return null;
        }
        return new Config($id, $snapshot->data());
    }

    public function save(Config $config): void
    {
        $this->db->collection($this->collectionName)->document($config->getId())->set($config->getData());
    }

    public function delete(string $id): void
    {
        $this->db->collection($this->collectionName)->document($id)->delete();
    }
}
