<?php

declare(strict_types=1);

namespace MyApp;

use Google\Cloud\Firestore\FirestoreClient;
use Google\Cloud\Firestore\DocumentReference;

class BotConfigsStore
{
    private FirestoreClient $dbAccessor;
    private DocumentReference $documentRoot;

    public function __construct(bool $isTest = true)
    {
        $this->dbAccessor = new FirestoreClient(["keyFilePath" => __DIR__ . '/../configs/firebase.json']);
        $collectionName = "ai-bot";
        if ($isTest) {
            $collectionName .= "-test";
        }
        $this->documentRoot = $this->dbAccessor->collection($collectionName)->document("configs");
    }

    public function get(string $targetId): ?BotConfig
    {
        return new BotConfig($this->documentRoot->collection($targetId), $this->getDefault());
    }
    public function getDefault(): BotConfig
    {
        return new BotConfig($this->documentRoot->collection("default"), null);
    }

    // public function exists(string $targetId): bool
    // {
    //     return !empty($this->get($targetId));
    // }
}
