<?php declare(strict_types=1);

namespace App\Infrastructure\Persistence\Firestore;

use Google\Cloud\Firestore\FirestoreClient;

abstract class AbstractFirestoreRepository
{
    protected FirestoreClient $db;
    protected string $collectionName;

    public function __construct(?FirestoreClient $db = null)
    {
        $this->collectionName = \App\AppConfig::getFirestoreRootCollection();
        $this->db = $db ?? new FirestoreClient(["keyFile" => json_decode(getenv("FIREBASE_SERVICE_ACCOUNT") ?: '[]', true)]);
    }
}
