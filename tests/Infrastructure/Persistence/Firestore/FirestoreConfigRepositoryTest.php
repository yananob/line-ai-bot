<?php declare(strict_types=1);

namespace Tests\Infrastructure\Persistence\Firestore;

use PHPUnit\Framework\TestCase;
use App\Infrastructure\Persistence\Firestore\FirestoreConfigRepository;
use App\Domain\Config\Config;
use Google\Cloud\Firestore\FirestoreClient;
use Google\Cloud\Firestore\CollectionReference;
use Google\Cloud\Firestore\DocumentReference;
use Google\Cloud\Firestore\DocumentSnapshot;
use Google\Cloud\Firestore\QuerySnapshot;

class FirestoreConfigRepositoryTest extends TestCase
{
    private $db;
    private $collection;
    private $repository;

    protected function setUp(): void
    {
        $this->db = $this->createMock(FirestoreClient::class);
        $this->collection = $this->createMock(CollectionReference::class);

        $this->db->method('collection')->with('config-test')->willReturn($this->collection);

        $this->repository = new FirestoreConfigRepository(true, $this->db);
    }

    public function testFindAll(): void
    {
        $doc1 = $this->createMock(DocumentSnapshot::class);
        $doc1->method('exists')->willReturn(true);
        $doc1->method('id')->willReturn('id1');
        $doc1->method('data')->willReturn(['foo' => 'bar']);

        $querySnapshot = $this->createMock(QuerySnapshot::class);
        $querySnapshot->method('getIterator')->willReturn(new \ArrayIterator([$doc1]));

        $this->collection->method('documents')->willReturn($querySnapshot);

        $results = $this->repository->findAll();

        $this->assertCount(1, $results);
        $this->assertInstanceOf(Config::class, $results[0]);
        $this->assertEquals('id1', $results[0]->getId());
        $this->assertEquals(['foo' => 'bar'], $results[0]->getData());
    }

    public function testFindById(): void
    {
        $docRef = $this->createMock(DocumentReference::class);
        $snapshot = $this->createMock(DocumentSnapshot::class);

        $this->collection->method('document')->with('id1')->willReturn($docRef);
        $docRef->method('snapshot')->willReturn($snapshot);
        $snapshot->method('exists')->willReturn(true);
        $snapshot->method('data')->willReturn(['foo' => 'bar']);

        $result = $this->repository->findById('id1');

        $this->assertNotNull($result);
        $this->assertEquals('id1', $result->getId());
        $this->assertEquals(['foo' => 'bar'], $result->getData());
    }

    public function testSave(): void
    {
        $docRef = $this->createMock(DocumentReference::class);
        $this->collection->method('document')->with('id1')->willReturn($docRef);

        $data = ['foo' => 'bar'];
        $config = new Config('id1', $data);

        $docRef->expects($this->once())->method('set')->with($data);

        $this->repository->save($config);
    }

    public function testDelete(): void
    {
        $docRef = $this->createMock(DocumentReference::class);
        $this->collection->method('document')->with('id1')->willReturn($docRef);

        $docRef->expects($this->once())->method('delete');

        $this->repository->delete('id1');
    }
}
