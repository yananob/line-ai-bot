<?php declare(strict_types=1);

namespace App\Infrastructure\Persistence\Firestore;

use App\Domain\Config\Config;
use App\Domain\Config\ConfigRepository;
use Google\Cloud\Firestore\FirestoreClient;

class FirestoreConfigRepository extends AbstractFirestoreRepository implements ConfigRepository
{
    private \Google\Cloud\Firestore\CollectionReference $configCollection;

    public function __construct(bool $isTest = true, ?FirestoreClient $db = null)
    {
        parent::__construct($isTest, $db);
        // The root should be common (e.g. /ai-bot or /ai-bot-test).
        // Under that, we have a 'config' document, which has a 'config' subcollection for CRUD entries.
        $this->configCollection = $this->db->collection($this->collectionName)->document('config')->collection('config');
    }

    public function findAll(): array
    {
        $documents = $this->configCollection->documents();
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
        $snapshot = $this->configCollection->document($id)->snapshot();
        if (!$snapshot->exists()) {
            return null;
        }
        return new Config($id, $snapshot->data());
    }

    public function save(Config $config): void
    {
        $this->configCollection->document($config->getId())->set($config->getData());
    }

    public function delete(string $id): void
    {
        $this->configCollection->document($id)->delete();
    }
}
