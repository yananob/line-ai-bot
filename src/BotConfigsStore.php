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
        $collectionName = "ai-bot" . ($isTest ? "-test" : "");
        $this->documentRoot = $this->dbAccessor->collection($collectionName)->document("configs");
    }

    public function getUsers(): array
    {
        $result = [];
        foreach ($this->documentRoot->collections() as $collectionReference) {
            if (in_array($collectionReference->id(), ["default"], true)) {
                continue;
            }
            $result[] = $this->getConfig($collectionReference->id());
        }
        return $result;
    }

    public function getConfig(string $targetId): ?BotConfig
    {
        return new BotConfig($this->documentRoot->collection($targetId), $this->getDefaultConfig());
    }
    public function getDefaultConfig(): BotConfig
    {
        return new BotConfig($this->documentRoot->collection("default"), null);
    }

    // public function exists(string $targetId): bool
    // {
    //     return !empty($this->get($targetId));
    // }
}
