<?php declare(strict_types=1);

namespace MyApp\Infrastructure\Persistence\Firestore;

use Google\Cloud\Firestore\FirestoreClient;
use Google\Cloud\Firestore\CollectionReference;
use Google\Cloud\Firestore\FieldValue;
use Google\Cloud\Firestore\Query;
use MyApp\Domain\Conversation\Conversation;
use MyApp\Domain\Conversation\ConversationRepository;
use DateTimeImmutable;
use DateTimeZone;

class FirestoreConversationRepository implements ConversationRepository
{
    private FirestoreClient $db;
    private string $collectionName;

    public function __construct(bool $isTest = true)
    {
        $this->collectionName = $isTest ? "ai-bot-test" : "ai-bot";
        $this->db = new FirestoreClient(["keyFilePath" => __DIR__ . '/../../../../configs/firebase.json']);
    }

    private function getBotConversationsCollection(string $botId): CollectionReference
    {
        // Path: /ai-bot-test/conversations/{botId}
        // or /ai-bot/conversations/{botId}
        return $this->db->collection($this->collectionName)
                        ->document('conversations')
                        ->collection($botId);
    }

    public function findByBotId(string $botId, int $limit = 20): array
    {
        $conversationsCollection = $this->getBotConversationsCollection($botId);
        $query = $conversationsCollection->orderBy('createdAt', Query::DIR_DESCENDING)->limit($limit);
        $documents = $query->documents();

        $conversations = [];
        foreach ($documents as $document) {
            if ($document->exists()) {
                $data = $document->data();
                $timestamp = $data['createdAt']; // This is a Google\Cloud\Core\Timestamp object
                
                // Convert Firestore Timestamp to DateTimeImmutable
                $dateTime = new DateTimeImmutable('@' . $timestamp->get()->getTimestamp());
                // Firestore Timestamps are UTC. Adjust to system's default timezone if necessary,
                // or ensure all DateTimeImmutable objects are handled consistently (e.g., kept in UTC).
                // For simplicity here, we assume UTC or consistent handling elsewhere.
                // $dateTime = $dateTime->setTimezone(new DateTimeZone(date_default_timezone_get()));


                $conversations[] = new Conversation(
                    $data['botId'],
                    $data['speaker'],
                    $data['content'],
                    $dateTime,
                    $document->id() // Use Firestore document ID as Conversation ID
                );
            }
        }
        // Since Firestore query is DESC, and we want most recent first,
        // if we were to reverse, we'd do it here. But DESC is correct.
        return $conversations;
    }

    public function save(Conversation $conversation): void
    {
        $conversationsCollection = $this->getBotConversationsCollection($conversation->getBotId());
        
        $data = [
            'botId'   => $conversation->getBotId(),
            'speaker' => $conversation->getSpeaker(),
            'content' => $conversation->getContent(),
            // Using Firestore Server Timestamp for createdAt ensures atomicity and correct ordering.
            // If $conversation->getCreatedAt() was set to a specific historical time,
            // you'd convert it to a Firestore Timestamp object instead of FieldValue::serverTimestamp().
            // For new conversations, server timestamp is usually appropriate.
            'createdAt' => FieldValue::serverTimestamp(),
        ];

        if ($conversation->getCreatedAt()->getTimestamp() !== (new DateTimeImmutable('@0'))->getTimestamp() && 
            $conversation->getCreatedAt()->getTimestamp() !== (new DateTimeImmutable())->getTimestamp()) {
             // If a specific createdAt (not Unix epoch or "now") was provided, use that.
             $data['createdAt'] = new \Google\Cloud\Core\Timestamp($conversation->getCreatedAt());
        }


        if ($conversation->getId() !== null) {
            // Update existing conversation
            $docRef = $conversationsCollection->document($conversation->getId());
            $docRef->set($data, ['merge' => true]); // Merge to not overwrite other fields if any
        } else {
            // Add new conversation, Firestore will generate an ID
            $docRef = $conversationsCollection->add($data);
            $conversation->setId($docRef->id()); // Set the ID back to the entity object
        }
    }

    public function deleteByBotId(string $botId, int $count): void
    {
        if ($count <= 0) {
            return;
        }

        $conversationsCollection = $this->getBotConversationsCollection($botId);
        // Fetch the 'count' most recent documents to delete them
        // Order by 'createdAt' descending, as this is our primary timestamp.
        $query = $conversationsCollection->orderBy('createdAt', Query::DIR_DESCENDING)->limit($count);
        $documents = $query->documents();

        $deletedCount = 0;
        foreach ($documents as $document) {
            if ($document->exists()) {
                $document->reference()->delete();
                $deletedCount++;
            }
        }
    }
}
