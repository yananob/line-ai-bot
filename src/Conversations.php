<?php

declare(strict_types=1);

namespace MyApp;

use Carbon\Carbon;
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
        foreach ($this->collectionRoot->orderBy("id", "DESC")->limit($count)->documents() as $doc) {
            // var_dump($doc);
            // $data = $doc->snapshot()->data();
            $data = $doc->data();
            $obj = new \stdClass();
            foreach (["id", "by", "content", "created_at"] as $key) {
                if ($key === "created_at") {
                    $obj->$key = new Carbon((string)$data[$key]);
                } else {
                    $obj->$key = $data[$key];
                }
            }
            $result[] = $obj;
            // if (--$count <= 0) {
            //     break;
            // }
        }
        // CacheStore::put(CacheItems::Accounts->value, $accounts);
        return $result;
    }

    public function store(string $by, string $content): void
    {
        $id = $this->collectionRoot->count() + 1;
        $this->collectionRoot->document($id)->set(
            [
                "id" => $id,
                "by" => $by,
                "content" => $content,
                "created_at" => FieldValue::serverTimestamp(),
            ]
        );
    }
}
