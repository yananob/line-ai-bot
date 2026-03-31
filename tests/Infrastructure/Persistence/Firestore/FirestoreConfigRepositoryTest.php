<?php declare(strict_types=1);

namespace Tests\Infrastructure\Persistence\Firestore;

use PHPUnit\Framework\TestCase;
use App\Infrastructure\Persistence\Firestore\FirestoreConfigRepository;
use Google\Cloud\Firestore\FirestoreClient;
use Google\Cloud\Firestore\CollectionReference;
use Google\Cloud\Firestore\DocumentReference;
use Google\Cloud\Firestore\DocumentSnapshot;
use Google\Cloud\Firestore\QuerySnapshot;

class FirestoreConfigRepositoryTest extends TestCase
{
    private $db;
    private $rootCollection;
    private $configsDocument;
    private $botCollection;
    private $configDoc;
    private $repository;

    protected function setUp(): void
    {
        $this->db = $this->createMock(FirestoreClient::class);
        $this->rootCollection = $this->createMock(CollectionReference::class);
        $this->configsDocument = $this->createMock(DocumentReference::class);
        $this->botCollection = $this->createMock(CollectionReference::class);
        $this->configDoc = $this->createMock(DocumentReference::class);

        $this->db->method('collection')->with('ai-bot-test')->willReturn($this->rootCollection);
        $this->rootCollection->method('document')->with('configs')->willReturn($this->configsDocument);
        $this->configsDocument->method('collection')->willReturn($this->botCollection);
        $this->botCollection->method('document')->with('config')->willReturn($this->configDoc);

        $this->repository = new FirestoreConfigRepository(true, $this->db);
    }

    public function testFindAllBotIds(): void
    {
        $this->configsDocument->method('collections')->willReturn([$this->botCollection]);
        $this->botCollection->method('id')->willReturn('bot-1');

        $results = $this->repository->findAllBotIds();

        $this->assertEquals(['bot-1'], $results);
    }

    public function testFindBotConfig(): void
    {
        $snapshot = $this->createMock(DocumentSnapshot::class);
        $this->configDoc->method('snapshot')->willReturn($snapshot);
        $snapshot->method('exists')->willReturn(true);
        $snapshot->method('data')->willReturn(['foo' => 'bar']);

        $result = $this->repository->findBotConfig('bot-1');

        $this->assertEquals(['foo' => 'bar'], $result);
    }

    public function testSaveBotConfig(): void
    {
        $data = ['foo' => 'bar'];
        $this->configDoc->expects($this->once())->method('set')->with($data);

        $this->repository->saveBotConfig('bot-1', $data);
    }
}
