<?php

declare(strict_types=1);

namespace MyApp\Tests\Infrastructure\Persistence\Firestore; // 名前空間を追加

use MyApp\Infrastructure\Persistence\Firestore\FirestoreConversationRepository;
use MyApp\Domain\Conversation\Conversation;
// use MyApp\Domain\Conversation\ConversationRepository; // インターフェースの型ヒント用 (このファイル内では直接使われていない模様)
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
    private $firestoreClientMock;
    private $collectionReferenceMock; // ルート 'ai-bot{-test}' コレクションのモック
    private $conversationsDocRefMock; // 'conversations' ドキュメントのモック
    private $botConversationsCollRefMock; // 特定のボットの会話サブコレクションのモック

    protected function setUp(): void
    {
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

        $this->repository = new FirestoreConversationRepository(isTest: true);
        $this->setPrivateProperty($this->repository, 'db', $this->firestoreClientMock);
    }

    // プライベートプロパティ設定用のヘルパーメソッド
    protected function setPrivateProperty($object, string $propertyName, $value): void
    {
        $reflection = new \ReflectionClass($object);
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true); // PHP8.1+ではsetAccessibleは不要な場合あり
        $property->setValue($object, $value);
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
            ->method('documents')->willReturn(new \ArrayObject([$docSnapshotMock2, $docSnapshotMock1]));

        $conversations = $this->repository->findByBotId($botId, $limit);

        $this->assertCount(2, $conversations);
        $this->assertInstanceOf(Conversation::class, $conversations[0]);
        $this->assertEquals('どうも', $conversations[0]->getContent());
        $this->assertEquals('bot', $conversations[0]->getSpeaker());
        $this->assertInstanceOf(Conversation::class, $conversations[1]);
        $this->assertEquals('こんにちは', $conversations[1]->getContent());
    }

    public function test_新規会話を保存する(): void
    {
        $botId = "testBotId";
        $conversation = new Conversation($botId, "human", "新しいメッセージ");

        $documentReferenceMock = $this->createMock(DocumentReference::class);
        $documentReferenceMock->method('id')->willReturn('newDocId');

        $this->botConversationsCollRefMock->expects($this->once())
            ->method('add')
            ->with($this->callback(function ($data) use ($botId, $conversation) {
                $this->assertEquals($botId, $data['botId']);
                $this->assertEquals("human", $data['speaker']);
                $this->assertEquals("新しいメッセージ", $data['content']);
                // FirestoreConversationRepositoryは、エンティティのcreatedAtが特定の日時でない場合、
                // 新規会話にはFieldValue::serverTimestamp()を使用します。
                // デフォルトのConversationコンストラクタはcreatedAtを "now" に設定します。
                // リポジトリの保存ロジックは、createdAtが "epoch" または "now" でないか確認し、そうでなければエンティティの時刻を使用します。
                // ここでは、デフォルトコンストラクタは "now" を意味するため、リポジトリはFieldValue::serverTimestamp()を使用するはずです。
                if ($conversation->getCreatedAt()->getTimestamp() === (new \DateTimeImmutable('@0'))->getTimestamp() ||
                    abs($conversation->getCreatedAt()->getTimestamp() - (new \DateTimeImmutable())->getTimestamp()) < 5 ) { // "now" のためのわずかな差を許容
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

    public function test_特定の過去タイムスタンプで新規会話を保存する(): void
    {
        $botId = "testBotId";
        $pastTime = CarbonImmutable::now()->subDays(5);
        $conversation = new Conversation($botId, "human", "過去のメッセージ", $pastTime); // 特定の過去時刻

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


    public function test_既存会話を保存する(): void
    {
        $botId = "testBotId";
        $existingId = "existingConvId";
        $now = CarbonImmutable::now();
        // 既存の会話の場合、FirestoreConversationRepositoryはcreatedAtが元のものと一致することを期待します。
        // そうでなければ上書きされます。現在のリポジトリの保存ロジックは、createdAtが "now" または "epoch" でない場合、
        // $data['createdAt'] = new Timestamp($conversation->getCreatedAt()) を使用します。
        $conversation = new Conversation($botId, "bot", "更新されたメッセージ", $now, $existingId);

        $documentReferenceMock = $this->createMock(DocumentReference::class);
        $this->botConversationsCollRefMock->expects($this->once())
            ->method('document')->with($existingId)->willReturn($documentReferenceMock);

        // 注意: FirestoreConversationRepositoryのsaveメソッドには'createdAt'に関する特定のロジックがあります。
        // $conversation->getCreatedAt()が非常に最近("now"に近い)場合、FieldValue::serverTimestamp()を使用する可能性があります。
        // 既存の会話の場合、通常は元の作成時刻を保持するか、明示的に更新します。
        // 現在のリポジトリロジック:
        // if ($conversation->getCreatedAt()->getTimestamp() !== (new DateTimeImmutable('@0'))->getTimestamp() &&
        //     $conversation->getCreatedAt()->getTimestamp() !== (new DateTimeImmutable())->getTimestamp()) {
        //      $data['createdAt'] = new \Google\Cloud\Core\Timestamp($conversation->getCreatedAt());
        // } else { $data['createdAt'] = FieldValue::serverTimestamp(); }
        // したがって、$nowが実際に "now" の場合、ServerTimestampを使用します。更新のために特定の保持されたタイムスタンプを渡すと仮定しましょう。
        // Timestampが使用されるようにするには、$nowが現在時刻に近すぎる場合は少し過去にします。
        $specificTimeForUpdate = CarbonImmutable::now()->subSeconds(10);
        $conversationForUpdate = new Conversation($botId, "bot", "更新されたメッセージ", $specificTimeForUpdate, $existingId);

        $expectedDataForUpdate = [
            'botId'   => $conversationForUpdate->getBotId(),
            'speaker' => $conversationForUpdate->getSpeaker(),
            'content' => $conversationForUpdate->getContent(),
            'createdAt' => new Timestamp($specificTimeForUpdate->toDateTime())
        ];


        $documentReferenceMock->expects($this->once())
            ->method('set')
            ->with($expectedDataForUpdate, ['merge' => true]); // 現在のリポジトリロジックによる

        $this->repository->save($conversationForUpdate);
    }

    public function test_botIdによる削除(): void
    {
        $botId = "testBotId";
        $count = 2;

        $docSnapshotMock1 = $this->createMock(DocumentSnapshot::class);
        $docRefMock1 = $this->createMock(DocumentReference::class);
        $docSnapshotMock1->method('exists')->willReturn(true); // deleteByBotIdのループにとって重要
        $docSnapshotMock1->method('reference')->willReturn($docRefMock1);

        $docSnapshotMock2 = $this->createMock(DocumentSnapshot::class);
        $docRefMock2 = $this->createMock(DocumentReference::class);
        $docSnapshotMock2->method('exists')->willReturn(true); // 重要
        $docSnapshotMock2->method('reference')->willReturn($docRefMock2);

        $this->botConversationsCollRefMock->expects($this->once())
            ->method('orderBy')->with('createdAt', Query::DIR_DESCENDING)->willReturnSelf();
        $this->botConversationsCollRefMock->expects($this->once())
            ->method('limit')->with($count)->willReturnSelf();
        $this->botConversationsCollRefMock->expects($this->once())
            ->method('documents')->willReturn(new \ArrayObject([$docSnapshotMock1, $docSnapshotMock2]));

        // バッチ操作のモック
        $writeBatchMock = $this->createMock(\Google\Cloud\Firestore\WriteBatch::class);
        $this->firestoreClientMock->expects($this->once())
            ->method('batch')
            ->willReturn($writeBatchMock);

        // 各ドキュメント参照に対してバッチでdeleteが呼び出されることを期待
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
        $this->botConversationsCollRefMock->method('documents')->willReturn(new \ArrayObject([]));

        $conversations = $this->repository->findByBotId($botId);
        $this->assertCount(0, $conversations);
        $this->assertEquals([], $conversations);
    }
}
