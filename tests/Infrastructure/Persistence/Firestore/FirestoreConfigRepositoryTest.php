<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Persistence\Firestore;

use App\Infrastructure\Persistence\Firestore\FirestoreConfigRepository;
use Google\Cloud\Firestore\FirestoreClient;
use Google\Cloud\Firestore\CollectionReference;
use Google\Cloud\Firestore\DocumentReference;
use Google\Cloud\Firestore\DocumentSnapshot;
use PHPUnit\Framework\TestCase;

final class FirestoreConfigRepositoryTest extends TestCase
{
    private FirestoreConfigRepository $repository;
    private \PHPUnit\Framework\MockObject\MockObject $firestoreClientMock;
    private \PHPUnit\Framework\MockObject\MockObject $rootCollectionMock;
    private \PHPUnit\Framework\MockObject\MockObject $documentRootMock;

    protected function setUp(): void
    {
        $this->firestoreClientMock = $this->createMock(FirestoreClient::class);
        $this->rootCollectionMock = $this->createMock(CollectionReference::class);
        $this->documentRootMock = $this->createMock(DocumentReference::class);

        $this->firestoreClientMock->method('collection')->willReturn($this->rootCollectionMock);
        $this->rootCollectionMock->method('document')->with('configs')->willReturn($this->documentRootMock);

        $this->repository = new FirestoreConfigRepository($this->firestoreClientMock);
    }

    public function test_findAllConfigs_success(): void
    {
        $botCollMock1 = $this->createMock(CollectionReference::class);
        $botCollMock1->method('id')->willReturn('bot-1');

        $botCollMock2 = $this->createMock(CollectionReference::class);
        $botCollMock2->method('id')->willReturn('bot-2');

        $this->documentRootMock->method('collections')->willReturn([$botCollMock1, $botCollMock2]);

        $configDocMock1 = $this->createMock(DocumentReference::class);
        $snapshotMock1 = $this->createMock(DocumentSnapshot::class);
        $snapshotMock1->method('exists')->willReturn(true);
        $snapshotMock1->method('data')->willReturn(['bot_name' => 'Name 1']);
        $configDocMock1->method('snapshot')->willReturn($snapshotMock1);

        $configDocMock2 = $this->createMock(DocumentReference::class);
        $snapshotMock2 = $this->createMock(DocumentSnapshot::class);
        $snapshotMock2->method('exists')->willReturn(false);
        $configDocMock2->method('snapshot')->willReturn($snapshotMock2);

        $botCollMock1->method('document')->with('config')->willReturn($configDocMock1);
        $botCollMock2->method('document')->with('config')->willReturn($configDocMock2);

        $configs = $this->repository->findAllConfigs();

        $this->assertCount(2, $configs);
        $this->assertEquals(['bot_name' => 'Name 1'], $configs['bot-1']);
        $this->assertEquals([], $configs['bot-2']);
    }

    public function test_saveBotConfig_success(): void
    {
        $botId = 'test-bot';
        $data = ['bot_name' => 'Test Name', 'bot_characteristics' => ['char1']];

        $botCollMock = $this->createMock(CollectionReference::class);
        $configDocMock = $this->createMock(DocumentReference::class);

        $this->documentRootMock->method('collection')->with($botId)->willReturn($botCollMock);
        $botCollMock->method('document')->with('config')->willReturn($configDocMock);

        $configDocMock->expects($this->once())->method('set')->with($data);

        $this->repository->saveBotConfig($botId, $data);
    }
}
