<?php

declare(strict_types=1);

namespace MyApp\Tests\Infrastructure\Persistence\Firestore;

use MyApp\Infrastructure\Persistence\Firestore\FirestoreBotRepository;
use MyApp\Domain\Bot\Bot;
use MyApp\Domain\Exception\BotNotFoundException;
use MyApp\Domain\Bot\Trigger\TimerTrigger;
use Google\Cloud\Firestore\FirestoreClient;
use Google\Cloud\Firestore\CollectionReference;
use Google\Cloud\Firestore\DocumentReference;
use Google\Cloud\Firestore\DocumentSnapshot;
use PHPUnit\Framework\TestCase;

final class FirestoreBotRepositoryTest extends TestCase
{
    private FirestoreBotRepository $repository;
    private $firestoreClientMock;
    private $rootCollectionMock;
    private $documentRootMock;

    protected function setUp(): void
    {
        $this->firestoreClientMock = $this->createMock(FirestoreClient::class);
        $this->rootCollectionMock = $this->createMock(CollectionReference::class);
        $this->documentRootMock = $this->createMock(DocumentReference::class);

        $this->firestoreClientMock->method('collection')->willReturn($this->rootCollectionMock);
        $this->rootCollectionMock->method('document')->with('configs')->willReturn($this->documentRootMock);

        $this->repository = new FirestoreBotRepository(isTest: true, db: $this->firestoreClientMock);
    }

    private function createBotMocks()
    {
        $botCollMock = $this->createMock(CollectionReference::class);
        $configDocMock = $this->createMock(DocumentReference::class);
        $snapshotMock = $this->createMock(DocumentSnapshot::class);
        $triggersDocMock = $this->createMock(DocumentReference::class);
        $triggersCollMock = $this->createMock(CollectionReference::class);

        $botCollMock->method('document')->willReturnCallback(function($id) use ($configDocMock, $triggersDocMock) {
            if ($id === 'config') return $configDocMock;
            if ($id === 'triggers') return $triggersDocMock;
            return null;
        });
        $configDocMock->method('snapshot')->willReturn($snapshotMock);
        $triggersDocMock->method('collection')->with('triggers')->willReturn($triggersCollMock);
        $triggersCollMock->method('documents')->willReturn(new \ArrayObject([]));

        return [$botCollMock, $configDocMock, $snapshotMock, $triggersDocMock, $triggersCollMock];
    }

    public function test_findDefaultが成功する(): void
    {
        $botId = 'default';
        [$botCollMock, $configDocMock, $snapshotMock] = $this->createBotMocks();

        $this->documentRootMock->method('collection')->with($botId)->willReturn($botCollMock);
        $snapshotMock->method('exists')->willReturn(true);
        $snapshotMock->method('data')->willReturn([
            'bot_characteristics' => ['char1'],
            'human_characteristics' => ['hchar1'],
            'requests' => ['req1'],
            'line_target' => 'target1'
        ]);

        $bot = $this->repository->findDefault();

        $this->assertInstanceOf(Bot::class, $bot);
        $this->assertEquals($botId, $bot->getId());
    }

    public function test_findByIdが成功する(): void
    {
        $botId = 'test-bot';

        $this->documentRootMock->method('collection')->willReturnCallback(function($id) use ($botId) {
            [$botCollMock, $configDocMock, $snapshotMock] = $this->createBotMocks();
            $snapshotMock->method('exists')->willReturn(true);
            if ($id === 'default') {
                $snapshotMock->method('data')->willReturn(['bot_characteristics' => ['default-char']]);
            } else {
                $snapshotMock->method('data')->willReturn(['bot_characteristics' => ['test-char']]);
            }
            return $botCollMock;
        });

        $bot = $this->repository->findById($botId);

        $this->assertInstanceOf(Bot::class, $bot);
        $this->assertEquals($botId, $bot->getId());
        $this->assertEquals(['test-char'], $bot->getBotCharacteristics());
    }

    public function test_findDefaultが失敗したときに例外を投げる(): void
    {
        $botId = 'default';
        [$botCollMock, $configDocMock, $snapshotMock] = $this->createBotMocks();

        $this->documentRootMock->method('collection')->with($botId)->willReturn($botCollMock);
        $snapshotMock->method('exists')->willReturn(false);

        $this->expectException(BotNotFoundException::class);
        $this->repository->findDefault();
    }

    public function test_saveが成功する(): void
    {
        $bot = new Bot('test-bot');
        $bot->setBotCharacteristics(['char']);

        [$botCollMock, $configDocMock] = $this->createBotMocks();

        $this->documentRootMock->method('collection')->with('test-bot')->willReturn($botCollMock);
        $configDocMock->expects($this->once())->method('set')->with($this->callback(function($data) {
            return $data['bot_characteristics'] === ['char'];
        }));

        $this->repository->save($bot);
    }
}
