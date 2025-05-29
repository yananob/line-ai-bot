<?php

declare(strict_types=1);

use MyApp\Infrastructure\Persistence\Firestore\FirestoreConversationRepository;
use MyApp\Domain\Conversation\Conversation;
use MyApp\Domain\Conversation\ConversationRepository; // For interface typehinting
use Google\Cloud\Firestore\FirestoreClient;
use Google\Cloud\Firestore\CollectionReference;
use Google\Cloud\Firestore\DocumentReference;
use Google\Cloud\Firestore\Query;
use Google\Cloud\Firestore\DocumentSnapshot;
use Google\Cloud\Firestore\FieldValue; // If testing server timestamps
use Google\Cloud\Core\Timestamp; // For creating Timestamp objects in test data
use Carbon\CarbonImmutable; // For creating DateTimeImmutable

final class FirestoreConversationRepositoryTest extends PHPUnit\Framework\TestCase
{
    private FirestoreConversationRepository $repository;
    private $firestoreClientMock;
    private $collectionReferenceMock; // Mock for the root 'ai-bot{-test}' collection
    private $conversationsDocRefMock; // Mock for the 'conversations' document
    private $botConversationsCollRefMock; // Mock for the specific bot's conversation subcollection

    protected function setUp(): void
    {
        $this->firestoreClientMock = $this->createMock(FirestoreClient::class);
        $this->collectionReferenceMock = $this->createMock(CollectionReference::class); // Mocks 'ai-bot-test'
        $this->conversationsDocRefMock = $this->createMock(DocumentReference::class); // Mocks 'conversations' doc
        $this->botConversationsCollRefMock = $this->createMock(CollectionReference::class); // Mocks '{botId}' subcollection

        // General mock setup for the path to a bot's conversation subcollection
        $this->firestoreClientMock->method('collection')
            ->willReturn($this->collectionReferenceMock); // Returns 'ai-bot-test' collection
        $this->collectionReferenceMock->method('document')
            ->with('conversations')
            ->willReturn($this->conversationsDocRefMock); // Returns 'conversations' document
        $this->conversationsDocRefMock->method('collection')
            ->willReturn($this->botConversationsCollRefMock); // Returns '{botId}' subcollection

        $this->repository = new FirestoreConversationRepository(isTest: true);
        $this->setPrivateProperty($this->repository, 'db', $this->firestoreClientMock);
    }

    protected function setPrivateProperty($object, string $propertyName, $value): void
    {
        $reflection = new \ReflectionClass($object);
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);
        $property->setValue($object, $value);
    }

    public function testFindByBotIdSuccessfullyFetchesConversations(): void
    {
        $botId = "testBotId";
        $limit = 2;

        $docSnapshotMock1 = $this->createMock(DocumentSnapshot::class);
        $docSnapshotMock1->method('exists')->willReturn(true);
        $docSnapshotMock1->method('id')->willReturn('doc1');
        $docSnapshotMock1->method('data')->willReturn([
            'botId' => $botId, 'speaker' => 'human', 'content' => 'Hello',
            'createdAt' => new Timestamp(CarbonImmutable::now()->subMinutes(10)->toDateTime())
        ]);

        $docSnapshotMock2 = $this->createMock(DocumentSnapshot::class);
        $docSnapshotMock2->method('exists')->willReturn(true);
        $docSnapshotMock2->method('id')->willReturn('doc2');
        $docSnapshotMock2->method('data')->willReturn([
            'botId' => $botId, 'speaker' => 'bot', 'content' => 'Hi there',
            'createdAt' => new Timestamp(CarbonImmutable::now()->subMinutes(5)->toDateTime())
        ]);
        
        // Setup expectations for the botConversationsCollRefMock specifically for this botId
        // This requires that the getBotConversationsCollection in the repository is called correctly
        // and that the $this->botConversationsCollRefMock is what it returns or is configured to return.
        // If getBotConversationsCollection is called with a specific botId to get this mock,
        // the setup in setUp() for conversationsDocRefMock->method('collection') needs to expect $botId.

        // Re-configuring the mock for this specific call if needed.
        // This setup is slightly simplified. A more complex setup might involve
        // $this->conversationsDocRefMock->method('collection')->with($botId)->willReturn($this->botConversationsCollRefMock);

        $this->botConversationsCollRefMock->expects($this->once())
            ->method('orderBy')->with('createdAt', Query::DIR_DESCENDING)->willReturnSelf();
        $this->botConversationsCollRefMock->expects($this->once())
            ->method('limit')->with($limit)->willReturnSelf();
        $this->botConversationsCollRefMock->expects($this->once())
            ->method('documents')->willReturn(new \ArrayObject([$docSnapshotMock2, $docSnapshotMock1]));

        $conversations = $this->repository->findByBotId($botId, $limit);

        $this->assertCount(2, $conversations);
        $this->assertInstanceOf(Conversation::class, $conversations[0]);
        $this->assertEquals('Hi there', $conversations[0]->getContent());
        $this->assertEquals('bot', $conversations[0]->getSpeaker());
        $this->assertInstanceOf(Conversation::class, $conversations[1]);
        $this->assertEquals('Hello', $conversations[1]->getContent());
    }

    public function testSaveNewConversation(): void
    {
        $botId = "testBotId";
        $conversation = new Conversation($botId, "human", "New message");

        $documentReferenceMock = $this->createMock(DocumentReference::class);
        $documentReferenceMock->method('id')->willReturn('newDocId');

        $this->botConversationsCollRefMock->expects($this->once())
            ->method('add')
            ->with($this->callback(function ($data) use ($botId, $conversation) {
                $this->assertEquals($botId, $data['botId']);
                $this->assertEquals("human", $data['speaker']);
                $this->assertEquals("New message", $data['content']);
                // FirestoreConversationRepository uses FieldValue::serverTimestamp() for new convos
                // if the entity's createdAt is not set to a specific past/future time.
                // The default Conversation constructor sets createdAt to "now".
                // The repo logic for save checks if createdAt is "epoch" or "now", if not, it uses the entity's time.
                // Here, default constructor means it's "now", so repo should use FieldValue::serverTimestamp().
                if ($conversation->getCreatedAt()->getTimestamp() === (new \DateTimeImmutable('@0'))->getTimestamp() || 
                    abs($conversation->getCreatedAt()->getTimestamp() - (new \DateTimeImmutable())->getTimestamp()) < 5 ) { // allow small diff for "now"
                    $this->assertInstanceOf(FieldValue::class, $data['createdAt']);
                } else {
                     $this->assertInstanceOf(Timestamp::class, $data['createdAt']);
                }
                return true;
            }))
            ->willReturn($documentReferenceMock);

        $this->repository->save($conversation);
        $this->assertEquals('newDocId', $conversation->getId());
    }
    
    public function testSaveNewConversationWithSpecificPastTimestamp(): void
    {
        $botId = "testBotId";
        $pastTime = CarbonImmutable::now()->subDays(5);
        $conversation = new Conversation($botId, "human", "Past message", $pastTime); // Specific past time

        $documentReferenceMock = $this->createMock(DocumentReference::class);
        $documentReferenceMock->method('id')->willReturn('newPastDocId');

        $this->botConversationsCollRefMock->expects($this->once())
            ->method('add') 
            ->with($this->callback(function ($data) use ($pastTime) {
                $this->assertInstanceOf(Timestamp::class, $data['createdAt']);
                $this->assertEquals($pastTime->getTimestamp(), $data['createdAt']->get()->getTimestamp());
                return true;
            }))
            ->willReturn($documentReferenceMock);
        
        $this->repository->save($conversation);
        $this->assertEquals('newPastDocId', $conversation->getId());
    }


    public function testSaveExistingConversation(): void
    {
        $botId = "testBotId";
        $existingId = "existingConvId";
        $now = CarbonImmutable::now();
        // For existing conversation, FirestoreConversationRepository expects createdAt to be the original one
        // or it will be overwritten. The current save logic in repo uses $data['createdAt'] = new Timestamp($conversation->getCreatedAt())
        // if the createdAt is not "now" or "epoch".
        $conversation = new Conversation($botId, "bot", "Updated message", $now, $existingId);

        $documentReferenceMock = $this->createMock(DocumentReference::class);
        $this->botConversationsCollRefMock->expects($this->once())
            ->method('document')->with($existingId)->willReturn($documentReferenceMock);
        
        $expectedData = [
            'botId'   => $botId,
            'speaker' => 'bot',
            'content' => 'Updated message',
            'createdAt' => new Timestamp($now->toDateTime()) // Firestore repo will use the entity's time
        ];
        // Note: FirestoreConversationRepository's save method has a specific logic for 'createdAt'.
        // If $conversation->getCreatedAt() is very recent (close to "now"), it might use FieldValue::serverTimestamp().
        // For an existing conversation, we typically want to preserve its original creation time or update it explicitly.
        // The current repo logic:
        // if ($conversation->getCreatedAt()->getTimestamp() !== (new DateTimeImmutable('@0'))->getTimestamp() && 
        //     $conversation->getCreatedAt()->getTimestamp() !== (new DateTimeImmutable())->getTimestamp()) {
        //      $data['createdAt'] = new \Google\Cloud\Core\Timestamp($conversation->getCreatedAt());
        // } else { $data['createdAt'] = FieldValue::serverTimestamp(); }
        // So, if $now is indeed "now", it will use ServerTimestamp. Let's assume for an update, we want to pass a specific preserved timestamp.
        // To ensure Timestamp is used, make $now slightly in the past if it's too close to current time.
        $specificTimeForUpdate = CarbonImmutable::now()->subSeconds(10);
        $conversationForUpdate = new Conversation($botId, "bot", "Updated message", $specificTimeForUpdate, $existingId);
        
        $expectedDataForUpdate = [
            'botId'   => $conversationForUpdate->getBotId(),
            'speaker' => $conversationForUpdate->getSpeaker(),
            'content' => $conversationForUpdate->getContent(),
            'createdAt' => new Timestamp($specificTimeForUpdate->toDateTime())
        ];


        $documentReferenceMock->expects($this->once())
            ->method('set')
            ->with($expectedDataForUpdate, ['merge' => true]); // As per current repo logic

        $this->repository->save($conversationForUpdate);
    }

    public function testDeleteByBotId(): void
    {
        $botId = "testBotId";
        $count = 2;

        $docSnapshotMock1 = $this->createMock(DocumentSnapshot::class);
        $docRefMock1 = $this->createMock(DocumentReference::class);
        $docSnapshotMock1->method('exists')->willReturn(true); // Important for the loop in deleteByBotId
        $docSnapshotMock1->method('reference')->willReturn($docRefMock1);

        $docSnapshotMock2 = $this->createMock(DocumentSnapshot::class);
        $docRefMock2 = $this->createMock(DocumentReference::class);
        $docSnapshotMock2->method('exists')->willReturn(true); // Important
        $docSnapshotMock2->method('reference')->willReturn($docRefMock2);

        $this->botConversationsCollRefMock->expects($this->once())
            ->method('orderBy')->with('createdAt', Query::DIR_DESCENDING)->willReturnSelf();
        $this->botConversationsCollRefMock->expects($this->once())
            ->method('limit')->with($count)->willReturnSelf();
        $this->botConversationsCollRefMock->expects($this->once())
            ->method('documents')->willReturn(new \ArrayObject([$docSnapshotMock1, $docSnapshotMock2]));

        // Mocking batch operations
        $writeBatchMock = $this->createMock(\Google\Cloud\Firestore\WriteBatch::class);
        $this->firestoreClientMock->expects($this->once())
            ->method('batch')
            ->willReturn($writeBatchMock);
        
        // Expect delete to be called on the batch for each document reference
        $writeBatchMock->expects($this->exactly(2))
            ->method('delete')
            ->withConsecutive(
                [$docRefMock1],
                [$docRefMock2]
            );

        $writeBatchMock->expects($this->once())
            ->method('commit');

        $this->repository->deleteByBotId($botId, $count);
    }


    public function testDeleteByBotIdDoesNothingIfCountIsZero(): void
    {
        $botId = "testBotId";
        $this->botConversationsCollRefMock->expects($this->never())->method('orderBy');
        $this->firestoreClientMock->expects($this->never())->method('batch');
        $this->repository->deleteByBotId($botId, 0);
    }

    public function testFindByBotIdReturnsEmptyForNonExistentOrEmptyConversations(): void
    {
        $botId = "emptyBotId";
        $this->botConversationsCollRefMock->method('orderBy')->willReturnSelf();
        $this->botConversationsCollRefMock->method('limit')->willReturnSelf();
        $this->botConversationsCollRefMock->method('documents')->willReturn(new \ArrayObject([]));

        $conversations = $this->repository->findByBotId($botId);
        $this->assertCount(0, $conversations);
        $this->assertEquals([], $conversations);
    }
}
