<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Persistence\Firestore;

use App\Infrastructure\Persistence\Firestore\FirestoreBotRepository;
use App\Domain\Bot\Bot;
use App\Domain\Bot\Trigger\TimerTrigger;
use App\Domain\Exception\BotNotFoundException;
use Google\Cloud\Firestore\FirestoreClient;
use Google\Cloud\Firestore\CollectionReference;
use Google\Cloud\Firestore\DocumentReference;
use Google\Cloud\Firestore\DocumentSnapshot;
use PHPUnit\Framework\TestCase;

final class FirestoreBotRepositoryTest extends TestCase
{
    private FirestoreBotRepository $repository;
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

        $this->repository = new FirestoreBotRepository(isTest: true, db: $this->firestoreClientMock);
    }

    private function createBotMocks(?iterable $triggerDocuments = null): array
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
        $triggersCollMock->method('documents')->willReturn($triggerDocuments ?? new \ArrayObject([]));

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

    public function test_findDefaultThrowsExceptionWhenNotFound(): void
    {
        $botId = 'default';
        [$botCollMock, $configDocMock, $snapshotMock] = $this->createBotMocks();

        $this->documentRootMock->method('collection')->with($botId)->willReturn($botCollMock);
        $snapshotMock->method('exists')->willReturn(false);

        $this->expectException(BotNotFoundException::class);
        $this->expectExceptionMessage("Default bot configuration with ID 'default' not found.");

        $this->repository->findDefault();
    }

    public function test_findByIdが成功しデフォルトと個別設定がマージされる(): void
    {
        $botId = 'test-bot';

        $this->documentRootMock->method('collection')->willReturnCallback(function($id) {
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
        $this->assertEquals(['default-char', 'test-char'], $bot->getBotCharacteristics()->toArray());
    }

    public function test_findByIdがトリガーをロードする(): void
    {
        $botId = 'test-bot';

        $mocks = [];

        $this->documentRootMock->method('collection')->willReturnCallback(function($id) use (&$mocks) {
            if (isset($mocks[$id])) {
                return $mocks[$id][0];
            }

            if ($id === 'default') {
                [$botCollMock, $configDocMock, $snapshotMock] = $this->createBotMocks();
                $snapshotMock->method('exists')->willReturn(true);
                $snapshotMock->method('data')->willReturn(['bot_characteristics' => ['default-char']]);
            } else {
                $triggerDocMock = $this->createMock(DocumentSnapshot::class);
                $triggerDocMock->method('exists')->willReturn(true);
                $triggerDocMock->method('id')->willReturn('trigger-id-123');
                $triggerDocMock->method('data')->willReturn([
                    'event' => 'timer',
                    'date' => 'today',
                    'time' => '12:00',
                    'request' => 'テストリクエスト'
                ]);

                [$botCollMock, $configDocMock, $snapshotMock, $triggersDocMock, $triggersCollMock] = $this->createBotMocks(new \ArrayObject([$triggerDocMock]));
                $snapshotMock->method('exists')->willReturn(true);
                $snapshotMock->method('data')->willReturn(['bot_characteristics' => ['test-char']]);
            }
            $mocks[$id] = [$botCollMock, $configDocMock, $snapshotMock];
            return $botCollMock;
        });

        $bot = $this->repository->findById($botId);

        $this->assertInstanceOf(Bot::class, $bot);
        $triggers = $bot->getTriggers();
        $this->assertCount(1, $triggers);
        $this->assertArrayHasKey('trigger-id-123', $triggers);
        $this->assertEquals('テストリクエスト', $triggers['trigger-id-123']->getRequest());
    }

    public function test_findByIdThrowsExceptionWhenNotFound(): void
    {
        $botId = 'non-existent-bot';

        $this->documentRootMock->method('collection')->willReturnCallback(function($id) {
            [$botCollMock, $configDocMock, $snapshotMock] = $this->createBotMocks();
            if ($id === 'non-existent-bot') {
                $snapshotMock->method('exists')->willReturn(false);
            } else {
                $snapshotMock->method('exists')->willReturn(true); // default bot exists
                $snapshotMock->method('data')->willReturn([]);
            }
            return $botCollMock;
        });

        $this->expectException(BotNotFoundException::class);
        $this->repository->findById($botId);
    }

    public function test_getAllUserBotsが成功する(): void
    {
        $botCollMock1 = $this->createMock(CollectionReference::class);
        $botCollMock1->method('id')->willReturn('user-bot-1');

        $botCollMock2 = $this->createMock(CollectionReference::class);
        $botCollMock2->method('id')->willReturn('default'); // ignore default

        $this->documentRootMock->method('collections')->willReturn([$botCollMock1, $botCollMock2]);

        // findById will be called for 'user-bot-1'
        $this->documentRootMock->method('collection')->willReturnCallback(function($id) {
            [$botCollMock, $configDocMock, $snapshotMock] = $this->createBotMocks();
            $snapshotMock->method('exists')->willReturn(true);
            $snapshotMock->method('data')->willReturn([]);
            return $botCollMock;
        });

        $bots = $this->repository->getAllUserBots();

        $this->assertCount(1, $bots);
        $this->assertEquals('user-bot-1', $bots[0]->getId());
    }

    public function test_saveが成功し個別設定のみ保存される(): void
    {
        $defaultBot = new Bot('default');
        $defaultBot->setBotCharacteristics(['default-char']);

        $bot = new Bot('test-bot', $defaultBot);
        $bot->setBotCharacteristics(['personal-char']);

        [$botCollMock, $configDocMock] = $this->createBotMocks();

        $this->documentRootMock->method('collection')->with('test-bot')->willReturn($botCollMock);
        $configDocMock->expects($this->once())->method('set')->with($this->callback(function($data) {
            // Should only contain 'personal-char', not 'default-char'
            return $data['bot_characteristics'] === ['personal-char'];
        }));

        $this->repository->save($bot);
    }
}
