<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Persistence\Firestore; // 名前空間を追加

use App\Infrastructure\Persistence\Firestore\FirestoreConversationRepository;
use App\Domain\Conversation\Conversation;
// use App\Domain\Conversation\ConversationRepository; // インターフェースの型ヒント用 (このファイル内では直接使われていない模様)
use Google\Cloud\Firestore\FirestoreClient;
use Google\Cloud\Firestore\CollectionReference;
use Google\Cloud\Firestore\DocumentReference;
use Google\Cloud\Firestore\Query;
use Google\Cloud\Firestore\DocumentSnapshot;
use Google\Cloud\Firestore\FieldValue; // サーバータイムスタンプのテスト用
use Google\Cloud\Core\Timestamp; // テストデータでTimestampオブジェクトを作成するため
use Carbon\CarbonImmutable; // DateTimeImmutableを作成するため
use PHPUnit\Framework\TestCase; // TestCaseをuse

final class FirestoreConversationRepositoryTest extends TestCase // TestCaseの完全修飾名を使用
{
    private FirestoreConversationRepository $repository;
    private \PHPUnit\Framework\MockObject\MockObject $firestoreClientMock;
    private \PHPUnit\Framework\MockObject\MockObject $collectionReferenceMock; // ルート 'ai-bot{-test}' コレクションのモック
    private \PHPUnit\Framework\MockObject\MockObject $conversationsDocRefMock; // 'conversations' ドキュメントのモック
    private \PHPUnit\Framework\MockObject\MockObject $botConversationsCollRefMock; // 特定のボットの会話サブコレクションのモック

    protected function setUp(): void
    {
        putenv('GCP_PROJECT=dummy-project');
        putenv('GOOGLE_APPLICATION_CREDENTIALS=dummy-credentials.json');
        $this->firestoreClientMock = $this->createMock(FirestoreClient::class);
        $this->collectionReferenceMock = $this->createMock(CollectionReference::class); // 'ai-bot-test' をモック
        $this->conversationsDocRefMock = $this->createMock(DocumentReference::class); // 'conversations' ドキュメントをモック
        $this->botConversationsCollRefMock = $this->createMock(CollectionReference::class); // '{botId}' サブコレクションをモック

        // ボットの会話サブコレクションへのパスの一般的なモック設定
        $this->firestoreClientMock->method('collection')
            ->willReturn($this->collectionReferenceMock); // 'ai-bot-test' コレクションを返す
        $this->collectionReferenceMock->method('document')
            ->with('conversations')
            ->willReturn($this->conversationsDocRefMock); // 'conversations' ドキュメントを返す
        $this->conversationsDocRefMock->method('collection')
            ->willReturn($this->botConversationsCollRefMock); // '{botId}' サブコレクションを返す

        $this->repository = new FirestoreConversationRepository(db: $this->firestoreClientMock);
    }

    public function test_botIdによる会話取得が成功する(): void
    {
        $botId = "testBotId";
        $limit = 2;

        $docSnapshotMock1 = $this->createMock(DocumentSnapshot::class);
        $docSnapshotMock1->method('exists')->willReturn(true);
        $docSnapshotMock1->method('id')->willReturn('doc1');
        $docSnapshotMock1->method('data')->willReturn([
            'botId' => $botId, 'speaker' => 'human', 'content' => 'こんにちは',
            'createdAt' => new Timestamp(CarbonImmutable::now()->subMinutes(10)->toDateTime())
        ]);

        $docSnapshotMock2 = $this->createMock(DocumentSnapshot::class);
        $docSnapshotMock2->method('exists')->willReturn(true);
        $docSnapshotMock2->method('id')->willReturn('doc2');
        $docSnapshotMock2->method('data')->willReturn([
            'botId' => $botId, 'speaker' => 'bot', 'content' => 'どうも',
            'createdAt' => new Timestamp(CarbonImmutable::now()->subMinutes(5)->toDateTime())
        ]);

        // このbotIdに特化したbotConversationsCollRefMockの期待値を設定
        // これには、リポジトリ内のgetBotConversationsCollectionが正しく呼び出され、
        // $this->botConversationsCollRefMockがその戻り値であるか、または返すように設定されている必要があります。
        // getBotConversationsCollectionが特定のbotIdで呼び出されてこのモックを取得する場合、
        // setUp()でのconversationsDocRefMock->method('collection')の設定は$botIdを期待する必要があります。

        // 必要であれば、この特定の呼び出しのためにモックを再設定します。
        // この設定は若干簡略化されています。より複雑な設定では、
        // $this->conversationsDocRefMock->method('collection')->with($botId)->willReturn($this->botConversationsCollRefMock); のようになるかもしれません。

        $this->botConversationsCollRefMock->expects($this->once())
            ->method('orderBy')->with('createdAt', Query::DIR_DESCENDING)->willReturnSelf();
        $this->botConversationsCollRefMock->expects($this->once())
            ->method('limit')->with($limit)->willReturnSelf();
        $this->botConversationsCollRefMock->expects($this->once())
            ->method('offset')->with(0)->willReturnSelf();
        $this->botConversationsCollRefMock->expects($this->once())
            ->method('documents')->willReturn(new \ArrayObject([$docSnapshotMock2, $docSnapshotMock1]));

        $conversations = $this->repository->findByBotId($botId, $limit);

        $this->assertCount(2, $conversations);
        $this->assertInstanceOf(Conversation::class, $conversations[0]);
        $this->assertEquals('どうも', $conversations[0]->getContent());
        $this->assertEquals('bot', $conversations[0]->getSpeaker());
        $this->assertInstanceOf(Conversation::class, $conversations[1]);
        $this->assertEquals('こんにちは', $conversations[1]->getContent());
    }

    public function test_新しい会話を保存する(): void
    {
        $conversation = new Conversation("testBotId", "human", "メッセージ");

        $newDocRefMock = $this->createMock(DocumentReference::class);
        $newDocRefMock->method('id')->willReturn('new-doc-id');

        $this->botConversationsCollRefMock->expects($this->once())
            ->method('add')
            ->with($this->callback(function($data) {
                return $data['botId'] === 'testBotId' && $data['content'] === 'メッセージ';
            }))
            ->willReturn($newDocRefMock);

        $this->repository->save($conversation);

        $this->assertEquals('new-doc-id', $conversation->getId());
    }

    public function test_既存の会話を保存する(): void
    {
        $conversation = new Conversation("testBotId", "human", "メッセージ", new \DateTimeImmutable(), "existing-id");

        $existingDocRefMock = $this->createMock(DocumentReference::class);
        $this->botConversationsCollRefMock->method('document')->with('existing-id')->willReturn($existingDocRefMock);

        $existingDocRefMock->expects($this->once())
            ->method('set')
            ->with($this->callback(function($data) {
                return $data['botId'] === 'testBotId';
            }), ['merge' => true]);

        $this->repository->save($conversation);
    }

    public function test_過去の日時を指定して会話を保存する(): void
    {
        $pastTime = new \DateTimeImmutable('2020-01-01 00:00:00');
        $conversation = new Conversation("testBotId", "human", "古いメッセージ", $pastTime);

        $newDocRefMock = $this->createMock(DocumentReference::class);
        $newDocRefMock->method('id')->willReturn('old-doc-id');

        $this->botConversationsCollRefMock->expects($this->once())
            ->method('add')
            ->with($this->callback(function($data) use ($pastTime) {
                return $data['createdAt'] instanceof \Google\Cloud\Core\Timestamp &&
                       $data['createdAt']->get()->getTimestamp() === $pastTime->getTimestamp();
            }))
            ->willReturn($newDocRefMock);

        $this->repository->save($conversation);
    }

    public function test_botIdによる削除が成功する(): void
    {
        $botId = "testBotId";
        $count = 1;

        $docReferenceMock = $this->createMock(DocumentReference::class);
        $docSnapshotMock = $this->createMock(DocumentSnapshot::class);
        $docSnapshotMock->method('exists')->willReturn(true);
        $docSnapshotMock->method('reference')->willReturn($docReferenceMock);

        $this->botConversationsCollRefMock->method('orderBy')->willReturnSelf();
        $this->botConversationsCollRefMock->method('limit')->with($count)->willReturnSelf();
        $this->botConversationsCollRefMock->method('documents')->willReturn(new \ArrayObject([$docSnapshotMock]));

        $docReferenceMock->expects($this->once())->method('delete');

        $this->repository->deleteByBotId($botId, $count);
    }

    public function test_カウントがゼロの場合にbotIdによる削除が何もしない(): void
    {
        $botId = "testBotId";
        $this->botConversationsCollRefMock->expects($this->never())->method('orderBy');
        $this->firestoreClientMock->expects($this->never())->method('batch');
        $this->repository->deleteByBotId($botId, 0);
    }

    public function test_存在しないまたは空の会話の場合にbotIdによる検索が空を返す(): void
    {
        $botId = "emptyBotId";
        $this->botConversationsCollRefMock->method('orderBy')->willReturnSelf();
        $this->botConversationsCollRefMock->method('limit')->willReturnSelf();
        $this->botConversationsCollRefMock->method('offset')->willReturnSelf();
        $this->botConversationsCollRefMock->method('documents')->willReturn(new \ArrayObject([]));

        $conversations = $this->repository->findByBotId($botId);
        $this->assertCount(0, $conversations);
        $this->assertEquals([], $conversations);
    }

    public function test_findByBotId_with_offset(): void
    {
        $botId = "testBotId";
        $limit = 10;
        $offset = 20;

        $this->botConversationsCollRefMock->expects($this->once())
            ->method('orderBy')->with('createdAt', Query::DIR_DESCENDING)->willReturnSelf();
        $this->botConversationsCollRefMock->expects($this->once())
            ->method('limit')->with($limit)->willReturnSelf();
        $this->botConversationsCollRefMock->expects($this->once())
            ->method('offset')->with($offset)->willReturnSelf();
        $this->botConversationsCollRefMock->expects($this->once())
            ->method('documents')->willReturn(new \ArrayObject([]));

        $this->repository->findByBotId($botId, $limit, $offset);
    }
}
