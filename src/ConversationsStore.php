<?php

declare(strict_types=1);

namespace MyApp;

use Carbon\Carbon;
use Google\Cloud\Firestore\FirestoreClient;
use Google\Cloud\Firestore\CollectionReference;
use Google\Cloud\Firestore\FieldValue;

class ConversationsStore
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

    // public function get(bool $includeBot = true, bool $includeHuman = true, int $count = 20): array
    public function get(int $count = 20): array
    {
        $result = [];
        foreach ($this->collectionRoot->orderBy("id", "DESC")->limit(50)->documents() as $doc) {
            $data = $doc->data();
            // if ($data["by"] === "bot" && !$includeBot) {
            //     continue;
            // }
            // if ($data["by"] === "human" && !$includeHuman) {
            //     continue;
            // }

            $obj = new \stdClass();
            foreach (["id", "by", "content", "created_at"] as $key) {
                if ($key === "created_at") {
                    $obj->$key = new Carbon((string)$data[$key]);
                } else {
                    $obj->$key = $data[$key];
                }
            }
            array_unshift($result, $obj);
            if (--$count <= 0) {
                break;
            }
        }
        return $result;
    }

    public function store(string $by, string $content): void
    {
        $id = $this->collectionRoot->count() + 1;
        $this->collectionRoot->document((string)$id)->set(
            [
                "id" => $id,
                "by" => $by,
                "content" => $content,
                "created_at" => FieldValue::serverTimestamp(),
            ]
        );
    }

    public function delete(int $count): void
    {
        foreach ($this->collectionRoot->orderBy("id", "DESC")->limit($count)->documents() as $doc) {
            $doc->reference()->delete();
        }
    }
}
