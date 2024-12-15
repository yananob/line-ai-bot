<?php

declare(strict_types=1);

namespace MyApp;

use Google\Cloud\Firestore\FirestoreClient;
use Google\Cloud\Firestore\CollectionReference;
use Google\Cloud\Firestore\FieldValue;

// use yananob\MyTools\CacheStore;
// use MyApp\CacheItems;

class Conversations
{
    private FirestoreClient $dbAccessor;
    private CollectionReference $collectionRoot;

    public function __construct(string $targetId, bool $isTest = true)
    {
        $this->dbAccessor = new FirestoreClient(["keyFilePath" => __DIR__ . '/../configs/firebase.json']);
        $collectionName = "ai-bot";
        if ($isTest) {
            $collectionName .= "-test";
        }
        $this->collectionRoot = $this->dbAccessor->collection($collectionName)->document("conversations")->collection($targetId);
    }

    public function get(int $count = 5): array
    {
        // $cache = CacheStore::get(CacheItems::Accounts->value);
        // if (!empty($cache)) {
        //     return $cache;
        // }

        $result = [];
        foreach ($this->collectionRoot->listDocuments() as $doc) {
            $data = $doc->snapshot()->data();
            $obj = new \stdClass();
            foreach (["by", "content", "created_at"] as $key) {
                $obj->$key = $data[$key];
            }
            $result[] = $obj;
        }
        // CacheStore::put(CacheItems::Accounts->value, $accounts);
        return $result;
    }

    public function store(string $by, string $content): void
    {
        $curCount = $this->collectionRoot->count();
        $this->collectionRoot->document($curCount + 1)->set(
            [
                "by" => $by,
                "content" => $content,
                "timestamp" => FieldValue::serverTimestamp(),
            ]
        );
    }
}
