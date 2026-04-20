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

        $this->repository = new FirestoreBotRepository($this->firestoreClientMock);
    }

    private function createBotMocks(?array $triggersData = null): array
    {
        $botCollMock = $this->createMock(CollectionReference::class);
        $configDocMock = $this->createMock(DocumentReference::class);
        $configSnapshotMock = $this->createMock(DocumentSnapshot::class);
        $triggersDocMock = $this->createMock(DocumentReference::class);
        $triggersSubCollMock = $this->createMock(CollectionReference::class);

        $botCollMock->method('document')->willReturnCallback(function($id) use ($configDocMock, $triggersDocMock) {
            if ($id === 'config') return $configDocMock;
            if ($id === 'triggers') return $triggersDocMock;
            return null;
        });

        $configDocMock->method('snapshot')->willReturn($configSnapshotMock);

        $triggersDocMock->method('collection')->with('triggers')->willReturn($triggersSubCollMock);

        if ($triggersData !== null) {
            $docs = [];
            foreach ($triggersData as $tid => $tdata) {
                $docMock = $this->createMock(DocumentSnapshot::class);
                $docMock->method('id')->willReturn($tid);
                $docMock->method('data')->willReturn($tdata);
                $docs[] = $docMock;
            }
            $triggersSubCollMock->method('documents')->willReturn($docs);
        } else {
            $triggersSubCollMock->method('documents')->willReturn([]);
        }

        return [$botCollMock, $configDocMock, $configSnapshotMock, $triggersDocMock, $triggersSubCollMock];
    }

    public function test_findDefault_success(): void
    {
        $botId = 'default';
        [$botCollMock, $configDocMock, $snapshotMock] = $this->createBotMocks();

        $this->documentRootMock->method('collection')->with($botId)->willReturn($botCollMock);
        $snapshotMock->method('exists')->willReturn(true);
        $snapshotMock->method('data')->willReturn([
            'bot_name' => 'Default Bot',
            'bot_characteristics' => ['char1'],
            'human_characteristics' => ['hchar1'],
            'requests' => ['req1'],
            'line_target' => 'target1'
        ]);

        $bot = $this->repository->findDefault();

        $this->assertInstanceOf(Bot::class, $bot);
        $this->assertEquals($botId, $bot->getId());
        $this->assertEquals('Default Bot', $bot->getName());
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

    public function test_findOrDefault_returns_existing_bot(): void
    {
        $botId = 'existing-bot';

        $this->documentRootMock->method('collection')->willReturnCallback(function($id) {
            [$botCollMock, $configDocMock, $snapshotMock] = $this->createBotMocks();
            $snapshotMock->method('exists')->willReturn(true);
            $snapshotMock->method('data')->willReturn(['bot_name' => 'Existing Bot']);
            return $botCollMock;
        });

        $bot = $this->repository->findOrDefault($botId);

        $this->assertInstanceOf(Bot::class, $bot);
        $this->assertEquals($botId, $bot->getId());
        $this->assertEquals('Existing Bot', $bot->getName());
    }

    public function test_findOrDefault_returns_new_bot_with_default_when_not_found(): void
    {
        $botId = 'non-existent-bot';

        $this->documentRootMock->method('collection')->willReturnCallback(function($id) use ($botId) {
            [$botCollMock, $configDocMock, $snapshotMock] = $this->createBotMocks();
            if ($id === $botId) {
                $snapshotMock->method('exists')->willReturn(false);
            } else {
                // default bot
                $snapshotMock->method('exists')->willReturn(true);
                $snapshotMock->method('data')->willReturn([
                    'bot_name' => 'Default Bot',
                    'line_target' => 'default-target'
                ]);
            }
            return $botCollMock;
        });

        $bot = $this->repository->findOrDefault($botId);

        $this->assertInstanceOf(Bot::class, $bot);
        $this->assertEquals($botId, $bot->getId());
        $this->assertEquals('', $bot->getName()); // New bot name is empty
        $this->assertEquals('default-target', $bot->getLineTarget()); // Taken from default bot
    }

    public function test_findById_success_and_merges_with_default(): void
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

    public function test_findById_loads_triggers(): void
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
                $triggerData = [
                    'trigger-id-123' => [
                        'event' => 'timer',
                        'date' => 'today',
                        'time' => '12:00',
                        'request' => 'テストリクエスト'
                    ]
                ];

                [$botCollMock, $configDocMock, $snapshotMock] = $this->createBotMocks($triggerData);
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

    public function test_getAllUserBots_success(): void
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

    public function test_save_success_and_saves_only_personal_settings(): void
    {
        $defaultBot = new Bot('default');
        $defaultBot->setBotCharacteristics(['default-char']);

        $bot = new Bot('test-bot', $defaultBot);
        $bot->setName('Personal Name');
        $bot->setBotCharacteristics(['personal-char']);

        [$botCollMock, $configDocMock] = $this->createBotMocks();

        $this->documentRootMock->method('collection')->with('test-bot')->willReturn($botCollMock);
        $configDocMock->expects($this->once())->method('set')->with($this->callback(function($data) {
            // Should contain 'Personal Name' and only 'personal-char', not 'default-char'
            return $data['bot_name'] === 'Personal Name' && $data['bot_characteristics'] === ['personal-char'];
        }));

        $this->repository->save($bot);
    }

    public function test_save_synchronizes_triggers_and_deletes_removed_ones(): void
    {
        $botId = 'test-bot';
        $bot = new Bot($botId);

        $trigger1 = new TimerTrigger('today', '10:00', 'Req 1');
        $bot->setTrigger('trigger-1', $trigger1);

        // Mock existing triggers in Firestore: trigger-1 and trigger-2
        $triggerData = [
            'trigger-1' => $trigger1->toArray(),
            'trigger-2' => ['event' => 'timer', 'date' => 'tomorrow', 'time' => '11:00', 'request' => 'Req 2']
        ];

        [$botCollMock, $configDocMock, $snapshotMock, $triggersDocMock, $triggersSubCollMock] = $this->createBotMocks($triggerData);

        $this->documentRootMock->method('collection')->with($botId)->willReturn($botCollMock);

        // trigger-2 should be deleted because it's in Firestore but not in the Bot object
        $trigger2DocRefMock = $this->createMock(DocumentReference::class);
        $trigger2DocRefMock->expects($this->once())->method('delete');

        // trigger-1 should be saved
        $trigger1DocRefMock = $this->createMock(DocumentReference::class);
        $trigger1DocRefMock->expects($this->once())->method('set');

        $triggersSubCollMock->method('document')->willReturnCallback(function($id) use ($trigger1DocRefMock) {
            if ($id === 'trigger-1') return $trigger1DocRefMock;
            return $this->createMock(DocumentReference::class);
        });

        // Our createBotMocks already sets documents() to return snapshots for trigger-1 and trigger-2
        // We need to ensure these snapshots return the correct reference() for deletion
        $docs = $triggersSubCollMock->documents();
        foreach ($docs as $doc) {
            $refMock = $this->createMock(DocumentReference::class);
            if ($doc->id() === 'trigger-2') {
                $refMock = $trigger2DocRefMock;
            }
            $doc->method('reference')->willReturn($refMock);
        }

        $this->repository->save($bot);
    }

    public function test_delete_success(): void
    {
        $botId = 'test-bot';
        $triggerData = [
            't1' => ['event' => 'timer']
        ];
        [$botCollMock, $configDocMock, $snapshotMock, $triggersDocMock, $triggersSubCollMock] = $this->createBotMocks($triggerData);

        $this->documentRootMock->method('collection')->with($botId)->willReturn($botCollMock);

        $configDocMock->expects($this->once())->method('delete');
        $triggersDocMock->expects($this->once())->method('delete');

        $t1DocRefMock = $this->createMock(DocumentReference::class);
        $t1DocRefMock->expects($this->once())->method('delete');

        $docs = $triggersSubCollMock->documents();
        $docs[0]->method('reference')->willReturn($t1DocRefMock);

        $this->repository->delete($botId);
    }
}
