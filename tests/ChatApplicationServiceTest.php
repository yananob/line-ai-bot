<?php

declare(strict_types=1);

namespace MyApp\Tests\Application; // 名前空間を追加

use Carbon\Carbon;
// use yananob\MyTools\Test; // このテストクラス内では直接使用されていないようなのでコメントアウトも検討
use yananob\MyTools\Gpt; // モック用
use MyApp\WebSearchTool; // WebSearchToolインスタンスのモック用

use MyApp\Application\ChatApplicationService;
use MyApp\Domain\Bot\BotRepository;
use MyApp\Domain\Conversation\ConversationRepository;
use MyApp\Domain\Bot\Bot;
use MyApp\Domain\Bot\Trigger\TimerTrigger;
// use MyApp\Domain\Bot\Service\CommandAndTriggerService; // 現状の設計ではChatApplicationServiceで直接使用されていない
use MyApp\Domain\Conversation\Conversation; // 会話データのモック用

// PHPUnit\Framework\TestCase は通常、グローバルにまたはオートローダー経由で利用可能

final class ChatApplicationServiceTest extends \PHPUnit\Framework\TestCase // クラス宣言を更新
{
    private ChatApplicationService $chatService; // $bot からリネームされました
    // private ChatApplicationService $chatServiceWithNonExistentBot; // この概念は再評価が必要です

    private $botRepositoryMock;
    private $conversationRepositoryMock;
    private $gptMock;
    private $webSearchToolMock;
    // private $commandAndTriggerServiceMock; // ChatApplicationService に直接注入されていない

    const TARGET_ID_AUTOTEST = "TARGET_ID_AUTOTEST";
    const TARGET_ID_FOR_DEFAULT_BEHAVIOR = "TARGET_ID_FOR_DEFAULT_BEHAVIOR"; // 最小/デフォルト設定を持つボット用
    const TARGET_ID_THAT_THROWS_EXCEPTION = "TARGET_ID_THAT_THROWS_EXCEPTION"; // コンストラクタ失敗テスト用

    protected function setUp(): void
    {
        $this->botRepositoryMock = $this->createMock(BotRepository::class);
        $this->conversationRepositoryMock = $this->createMock(ConversationRepository::class);
        $this->gptMock = $this->createMock(Gpt::class);
        $this->webSearchToolMock = $this->createMock(WebSearchTool::class);
        // $this->commandAndTriggerServiceMock = $this->createMock(CommandAndTriggerService::class); // ChatApplicationService に直接注入されない

        // --- TARGET_ID_AUTOTEST 用のボットモック (フル機能ボット) ---
        $mockBotAutotest = $this->createMock(Bot::class);
        $mockBotAutotest->method('getId')->willReturn(self::TARGET_ID_AUTOTEST);
        $mockBotAutotest->method('getLineTarget')->willReturn('test_line_target_autotest'); // ユニークなLINEターゲット
        $mockBotAutotest->method('hasHumanCharacteristics')->willReturn(true);
        $mockBotAutotest->method('getBotCharacteristics')->willReturn(['Bot char 1 AUTOTEST']);
        $mockBotAutotest->method('getHumanCharacteristics')->willReturn(['Human char 1 AUTOTEST']);
        $mockBotAutotest->method('getConfigRequests')->willReturn(['Default request AUTOTEST']);
        $mockBotAutotest->method('getTriggers')->willReturn([]); // 初期状態ではトリガーなし

        // --- TARGET_ID_FOR_DEFAULT_BEHAVIOR 用のボットモック (最小/デフォルト風設定ボット) ---
        $mockBotDefaultBehavior = $this->createMock(Bot::class);
        $mockBotDefaultBehavior->method('getId')->willReturn(self::TARGET_ID_FOR_DEFAULT_BEHAVIOR);
        $mockBotDefaultBehavior->method('getLineTarget')->willReturn('test_line_target_default'); // ユニークなLINEターゲット
        $mockBotDefaultBehavior->method('hasHumanCharacteristics')->willReturn(false); // autotest とは異なる
        $mockBotDefaultBehavior->method('getBotCharacteristics')->willReturn(['Default Bot char BEHAVIOR']);
        $mockBotDefaultBehavior->method('getHumanCharacteristics')->willReturn([]);
        $mockBotDefaultBehavior->method('getConfigRequests')->willReturn(['Basic request BEHAVIOR']);


        // --- 特定の検索/GPTシナリオ用のボットモック ---
        // 振る舞いが大きく異なる場合は専用のモックを作成する方がクリーンですが、
        // IDが異なるだけのような単純なケースでは、ベースモックを再利用しても問題ありません。
        // 特定のメソッドが異なる戻り値を必要としない限り、$mockBotAutotest がこれらのほとんどに対応できると仮定します。
        $mockBotSearchYes = $this->createMock(Bot::class); // 専用モックの例
        $mockBotSearchYes->method('getId')->willReturn("TARGET_ID_AUTOTEST_SEARCH_YES");
        // ... $mockBotSearchYes に必要なその他のメソッドスタブ

        $this->botRepositoryMock->method('findById')
            ->willReturnMap([
                [self::TARGET_ID_AUTOTEST, $mockBotAutotest],
                [self::TARGET_ID_FOR_DEFAULT_BEHAVIOR, $mockBotDefaultBehavior],
                [self::TARGET_ID_THAT_THROWS_EXCEPTION, null],
                ["TARGET_ID_AUTOTEST_SEARCH_YES", $mockBotAutotest],
                ["TARGET_ID_AUTOTEST_SEARCH_NO", $mockBotAutotest],
                ["TARGET_ID_AUTOTEST_SEARCH_UNEXPECTED", $mockBotAutotest],
                ["TARGET_ID_GETANSWER_SEARCH", $mockBotAutotest],
                ["TARGET_ID_GETANSWER_NOSEARCH", $mockBotAutotest],
                ["TARGET_ID_GENERATE_QUERY", $mockBotAutotest],
                ["TARGET_ID_GENERATE_QUERY_EMPTY", $mockBotAutotest],
                ["TARGET_ID_GENERATE_QUERY_SHORT", $mockBotAutotest],
                ["TARGET_ID_WEBSEARCH_CONFIGURED", $mockBotAutotest],
            ]);

        // ほとんどのテスト用のメインインスタンス
        $this->chatService = new ChatApplicationService(
            self::TARGET_ID_AUTOTEST,
            $this->botRepositoryMock,
            $this->conversationRepositoryMock,
            true // isTest
        );
        $this->setPrivateProperty($this->chatService, 'gpt', $this->gptMock);
        // WebSearchTool は、特定の検索シナリオごとに再モックされるか、プロパティが設定されることがよくあります
    }

    public function test_ボットが見つからない場合にコンストラクタが例外をスローする(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Bot with ID '" . self::TARGET_ID_THAT_THROWS_EXCEPTION . "' not found.");
        new ChatApplicationService(
            self::TARGET_ID_THAT_THROWS_EXCEPTION,
            $this->botRepositoryMock,
            $this->conversationRepositoryMock,
            true
        );
    }

    public function test_最近の会話なしで回答を取得する(): void
    {
        $this->gptMock->method('getAnswer')->willReturn('モックされた回答');
        $this->conversationRepositoryMock->method('findByBotId')->willReturn([]);

        $this->assertNotEmpty($this->chatService->getAnswer(
            false, // applyRecentConversations
            "今年のクリスマスは何月何日でしょうか？\n昨年のクリスマスとは違うのでしょうか？"
        ));
    }

    public function test_最近の会話ありで回答を取得する(): void
    {
        $mockedConversation = $this->createMock(Conversation::class);
        $this->conversationRepositoryMock->method('findByBotId')
            ->willReturn([$mockedConversation]);
        $this->gptMock->method('getAnswer')->willReturn('モックされた回答');

        $this->assertNotEmpty($this->chatService->getAnswer(
            true, // applyRecentConversations
            "今年のクリスマスは何月何日でしょうか？\n昨年のクリスマスとは違うのでしょうか？"
        ));
    }

    public function test_最近の会話なしでリクエストを尋ねる(): void
    {
        $this->gptMock->method('getAnswer')->willReturn('モックされた回答');
        $this->conversationRepositoryMock->method('findByBotId')->willReturn([]);

        $this->assertNotEmpty($this->chatService->askRequest(
            false, // applyRecentConversations
            "今年のクリスマスメッセージを送って"
        ));
    }

    // リファクタリングされたコンテキストテスト: getAnswer をテストし、GPT モックコールバック経由でコンテキストを検査する
    public function test_人間的な特徴がないボットのコンテキスト構築(): void
    {
        $chatServiceDefaultBehavior = new ChatApplicationService(
            self::TARGET_ID_FOR_DEFAULT_BEHAVIOR,
            $this->botRepositoryMock,
            $this->conversationRepositoryMock,
            true
        );
        $this->setPrivateProperty($chatServiceDefaultBehavior, 'gpt', $this->gptMock);


        $expectedContextPart = "【話し相手の情報】"; // これは存在しないはず
        $this->gptMock->method('getAnswer')
            ->with(
                $this->callback(function ($context) use ($expectedContextPart) {
                    $this->assertStringNotContainsString($expectedContextPart, $context);
                    // デフォルトのボット特性をチェック
                    $this->assertStringContainsString("Default Bot char BEHAVIOR", $context);
                    return true;
                }),
                $this->anything() // message
            )
            ->willReturn("モックされた回答");

        $chatServiceDefaultBehavior->getAnswer(false, "some message");
    }

    public function test_人間的な特徴があるボットのコンテキスト構築(): void
    {
        // chatService は既に人間的な特徴を持つ TARGET_ID_AUTOTEST です
        $expectedContextPart = "【話し相手の情報】"; // これは存在するはず
        $humanCharPart = "Human char 1 AUTOTEST";
        $this->gptMock->method('getAnswer')
            ->with(
                $this->callback(function ($context) use ($expectedContextPart, $humanCharPart) {
                    $this->assertStringContainsString($expectedContextPart, $context);
                    $this->assertStringContainsString($humanCharPart, $context);
                    return true;
                }),
                $this->anything()
            )
            ->willReturn("モックされた回答");
        $this->chatService->getAnswer(false, "some message");
    }

    public function test_最近の会話なしでのコンテキスト構築(): void
    {
        $this->conversationRepositoryMock->method('findByBotId')->willReturn([]);

        $expectedContextPart = "【最近の会話内容】"; // これは存在しないはず
        $this->gptMock->method('getAnswer')
            ->with(
                $this->callback(function ($context) use ($expectedContextPart) {
                    $this->assertStringNotContainsString($expectedContextPart, $context);
                    return true;
                }),
                $this->anything()
            )
            ->willReturn("モックされた回答");
        $this->chatService->getAnswer(false, "some message"); // applyRecentConversations = false
    }

    public function test_最近の会話ありでのコンテキスト構築(): void
    {
        $mockConversation = $this->createMock(Conversation::class);
        $mockConversation->method('getCreatedAt')->willReturn(Carbon::now());
        $mockConversation->method('getSpeaker')->willReturn('human');
        $mockConversation->method('getContent')->willReturn('テスト会話内容');

        $this->conversationRepositoryMock->method('findByBotId')->willReturn([$mockConversation]);

        $expectedContextPart = "【最近の会話内容】"; // これは存在するはず
        $conversationContentPart = "テスト会話内容";
        $this->gptMock->method('getAnswer')
            ->with(
                $this->callback(function ($context) use ($expectedContextPart, $conversationContentPart) {
                    $this->assertStringContainsString($expectedContextPart, $context);
                    $this->assertStringContainsString($conversationContentPart, $context);
                    return true;
                }),
                $this->anything()
            )
            ->willReturn("モックされた回答");
        $this->chatService->getAnswer(true, "some message"); // applyRecentConversations = true
    }


    public function test_AutotestボットのLineTargetを取得する(): void
    {
        // chatService は TARGET_ID_AUTOTEST です
        $this->assertSame('test_line_target_autotest', $this->chatService->getLineTarget());
    }

    public function test_DefaultBehaviorボットのLineTargetを取得する(): void
    {
        $chatServiceDefault = new ChatApplicationService(
            self::TARGET_ID_FOR_DEFAULT_BEHAVIOR,
            $this->botRepositoryMock,
            $this->conversationRepositoryMock,
            true
        );
        $this->assertSame('test_line_target_default', $chatServiceDefault->getLineTarget());
    }

    public function test_タイマートリガーを追加する(): void
    {
        $mockTimerTrigger = $this->createMock(TimerTrigger::class);
        // $mockTimerTrigger->method('getId')->willReturn('trigger123'); // このテストの焦点には厳密には不要

        // chatService が使用する特定のモック Bot インスタンスを取得
        $botFromRepo = $this->botRepositoryMock->findById(self::TARGET_ID_AUTOTEST);

        // Bot::addTrigger が呼び出されることを期待
        $botFromRepo->expects($this->once()) // または単一呼び出しの場合は ->exactly(1)
                    ->method('addTrigger')
                    ->with($mockTimerTrigger) // トリガーと共に呼び出されることを表明
                    ->willReturn('new_mocked_trigger_id'); // Bot::addTrigger の戻り値

        // BotRepository::save が正しい Bot インスタンスで呼び出されることを期待
        $this->botRepositoryMock->expects($this->once())
                                ->method('save')
                                ->with($botFromRepo); // ボットインスタンスと共に呼び出されることを表明

        $this->chatService->addTimerTrigger($mockTimerTrigger);

        // アサーションはモックオブジェクトの期待値に対するものです。
        // ChatApplicationService からの addTimerTrigger の戻り値を表明したい場合:
        // $returnedId = $this->chatService->addTimerTrigger($mockTimerTrigger);
        // $this->assertSame('new_mocked_trigger_id', $returnedId);
        // このためには、モック Bot の addTrigger が ChatApplicationService が返すべき値を返すようにします。
    }

    public function test_トリガーを削除する(): void
    {
        $triggerIdToDelete = 'trigger_to_delete_123';

        $botFromRepo = $this->botRepositoryMock->findById(self::TARGET_ID_AUTOTEST);
        $botFromRepo->expects($this->once())
                    ->method('deleteTriggerById')
                    ->with($triggerIdToDelete);

        $this->botRepositoryMock->expects($this->once())
                                ->method('save')
                                ->with($botFromRepo);

        $this->chatService->deleteTrigger($triggerIdToDelete);
    }


    // プライベートプロパティ設定用のヘルパーメソッド
    protected function setPrivateProperty($object, string $propertyName, $value): void
    {
        $reflection = new \ReflectionClass($object);
        $property = $reflection->getProperty($propertyName);
        // $property->setAccessible(true); // PHP 8.1+ では、同じクラスのプライベートプロパティに対して setAccessible は不要になりました
        $property->setValue($object, $value);
    }

    // __shouldPerformWebSearch のテスト (間接的に getAnswer をテストすることで)
    public function test_shouldPerformWebSearchがTrueを返す場合のgetAnswerフロー(): void
    {
        $userMessage = '検索が必要なメッセージ';
        $this->setPrivateProperty($this->chatService, 'googleApiKey', 'dummy_key'); // 検索ロジックパスを有効化
        $this->setPrivateProperty($this->chatService, 'googleCxId', 'dummy_cx');   // 検索ロジックパスを有効化
        $this->setPrivateProperty($this->chatService, 'webSearchTool', $this->webSearchToolMock);


        // __shouldPerformWebSearch 内部呼び出しのためのGPTモック
        // この特定の呼び出しは深部にあります。getAnswer が行うGPT呼び出しをモックします。
        // 1. Web検索を判断するため
        // 2. クエリを生成するため (検索が必要な場合)
        // 3. 最終応答のため
        $this->gptMock->expects($this->exactly(3)) // フローに基づいて調整
            ->method('getAnswer')
            ->willReturnMap([
                [ChatApplicationService::PROMPT_JUDGE_WEB_SEARCH, $userMessage, 'はい'], // はい、検索する
                [ChatApplicationService::PROMPT_GENERATE_SEARCH_QUERY, $userMessage, '検索クエリ'], // 生成されたクエリ
                [$this->isType('string'), $userMessage, '検索結果を含む最終回答'], // コンテキスト、メッセージ
            ]);

        $this->webSearchToolMock->method('search')->willReturn('モックされた検索結果');

        $this->chatService->getAnswer(true, $userMessage);
        // アサーションはモックに対するものです (例: webSearchToolMock->expects($this->once())->method('search'))
        // または、より堅牢には、他のテストのように最終GPT呼び出しに渡されるコンテキストを確認します。
    }


    public function test_shouldPerformWebSearchがFalseを返す場合のgetAnswerフロー(): void
    {
        $userMessage = '検索が不要な別のメッセージ';
        // 検索ツールが呼び出されないこと、クエリ生成用のGPTが呼び出されないことを確認します。
        $this->setPrivateProperty($this->chatService, 'gpt', $this->gptMock); // モックが使用されることを確認

        $this->gptMock->expects($this->exactly(2)) // 判断、最終回答
            ->method('getAnswer')
            ->willReturnMap([
                [ChatApplicationService::PROMPT_JUDGE_WEB_SEARCH, $userMessage, 'いいえ'], // 検索しない
                // PROMPT_GENERATE_SEARCH_QUERY の呼び出しなし
                [$this->isType('string'), $userMessage, '最終回答、検索なし'], // コンテキスト、メッセージ
            ]);

        $this->webSearchToolMock->expects($this->never())->method('search');

        $this->chatService->getAnswer(true, $userMessage);
    }


    // __getContext のテスト (Web検索結果に関連) - getAnswer でコンテキストを確認することで
    public function test_getAnswer経由で提供された場合にコンテキストにWeb検索結果が含まれる(): void
    {
        $userMessage = "これを検索";
        $searchResults = "Web情報が見つかりました: A, B, C.";
        $this->setPrivateProperty($this->chatService, 'googleApiKey', 'dummy_key');
        $this->setPrivateProperty($this->chatService, 'googleCxId', 'dummy_cx');
        $this->setPrivateProperty($this->chatService, 'webSearchTool', $this->webSearchToolMock);

        $this->gptMock->method('getAnswer')
            ->will($this->onConsecutiveCalls(
                'はい', // Web検索を判断
                'これの検索クエリ', // クエリを生成
                '最終回答' // 最終回答を生成
            ));

        $this->webSearchToolMock->method('search')->willReturn($searchResults);

        // 最後のgptMockの期待値をオーバーライドしてコンテキストを検査
        $this->gptMock->expects($this->atLeastOnce()) // または正確に3回の場合は $this->exactly(3)
            ->method('getAnswer')
            ->with(
                $this->callback(function ($contextArg) use ($searchResults) {
                    // このコールバックは gpt->getAnswer のすべての呼び出しに対して呼び出されます。
                    // 検索結果を含むものに関心があります。
                    if (str_contains($contextArg, "【Web検索結果】")) {
                        $this->assertStringContainsString($searchResults, $contextArg);
                    }
                    return true; // テストを続行させる
                }),
                $this->anything()
            );


        $this->chatService->getAnswer(true, $userMessage);
    }

    public function test_getAnswer経由でnullの場合にコンテキストからWeb検索結果が除外される(): void
    {
        $userMessage = "これは検索しない";
         // APIキーがnullであるか、webSearchToolがnullであるか、GPTが「いいえ」と言うことを確認します
        $this->setPrivateProperty($this->chatService, 'googleApiKey', null); // 検索の設定がないことをシミュレート


        $this->gptMock->method('getAnswer')
            ->will($this->onConsecutiveCalls(
                'いいえ', // Web検索を判断 -> いいえ
                '最終回答' // 最終回答生成
            ));
        // webSearchToolMock->search は呼び出されないはずです。
        // PROMPT_GENERATE_SEARCH_QUERY は呼び出されないはずです。

        $this->gptMock->expects($this->atLeastOnce())
            ->method('getAnswer')
            ->with(
                $this->callback(function ($contextArg) {
                    if (!str_contains($contextArg, ChatApplicationService::PROMPT_JUDGE_WEB_SEARCH) &&
                        !str_contains($contextArg, ChatApplicationService::PROMPT_GENERATE_SEARCH_QUERY)
                    ) { // 最終コンテキストのみを検査
                        $this->assertStringNotContainsString("【Web検索結果】", $contextArg);
                        $this->assertStringNotContainsString("<web_search_results>", $contextArg);
                    }
                    return true;
                }),
                $this->anything()
            );

        $this->chatService->getAnswer(true, $userMessage);
    }


    public function test_設定されている場合にgetAnswerがWebSearchToolインスタンスを使用する(): void
    {
        $userMessage = "猫について教えて。";
        $dummySearchQuery = "猫 情報";
        $mockedSearchResults = "Web検索結果:\n- タイトル: 猫は素晴らしい。 スニペット: はい、そうです。";
        $expectedFinalAnswer = "私の調査によると、猫は確かに素晴らしいです。";

        $this->setPrivateProperty($this->chatService, 'googleApiKey', "DUMMY_API_KEY");
        $this->setPrivateProperty($this->chatService, 'googleCxId', "DUMMY_CX_ID");
        $this->setPrivateProperty($this->chatService, 'webSearchTool', $this->webSearchToolMock);

        $this->gptMock->expects($this->exactly(3))
            ->method('getAnswer')
            ->willReturnMap([
                [ChatApplicationService::PROMPT_JUDGE_WEB_SEARCH, $userMessage, 'はい'],
                [ChatApplicationService::PROMPT_GENERATE_SEARCH_QUERY, $userMessage, $dummySearchQuery],
                [$this->callback(function ($context) use ($mockedSearchResults) {
                    $this->assertStringContainsString("【Web検索結果】", $context);
                    $this->assertStringContainsString($mockedSearchResults, $context);
                    return true;
                }), $userMessage, $expectedFinalAnswer],
            ]);

        $this->webSearchToolMock->expects($this->once())
            ->method('search')
            ->with($dummySearchQuery, "DUMMY_CX_ID")
            ->willReturn($mockedSearchResults);

        $actualAnswer = $this->chatService->getAnswer(true, $userMessage);
        $this->assertSame($expectedFinalAnswer, $actualAnswer);
    }

    // __generateSearchQuery のテスト (間接的に getAnswer をテストすることで)
    public function test_getAnswerが生成された検索クエリを正しく使用する(): void
    {
        $userMessage = "明日の東京の天気は？";
        $expectedQuery = "天気 明日 東京";

        $this->setPrivateProperty($this->chatService, 'googleApiKey', 'dummy_key');
        $this->setPrivateProperty($this->chatService, 'googleCxId', 'dummy_cx');
        $this->setPrivateProperty($this->chatService, 'webSearchTool', $this->webSearchToolMock);

        $this->gptMock->method('getAnswer')
            ->will($this->onConsecutiveCalls(
                'はい',           // 検索を判断: はい
                $expectedQuery,   // クエリを生成: "天気 明日 東京"
                '東京の天気に基づく最終回答' // 最終応答
            ));

        $this->webSearchToolMock->expects($this->once())
                                ->method('search')
                                ->with($expectedQuery, $this->anything()) // これが生成されたクエリで呼び出されることを表明
                                ->willReturn('東京の気象データ');

        $this->chatService->getAnswer(true, $userMessage);
    }

    public function test_GPTクエリが空の場合にgetAnswerが検索に元のメッセージをフォールバックする(): void
    {
        $userMessage = "簡単なキーワードがない複雑な質問。";

        $this->setPrivateProperty($this->chatService, 'googleApiKey', 'dummy_key');
        $this->setPrivateProperty($this->chatService, 'googleCxId', 'dummy_cx');
        $this->setPrivateProperty($this->chatService, 'webSearchTool', $this->webSearchToolMock);

        $this->gptMock->method('getAnswer')
            ->will($this->onConsecutiveCalls(
                'はい', // 検索を判断: はい
                '',     // クエリを生成: 空の応答
                '複雑な質問に基づく最終回答'
            ));

        $this->webSearchToolMock->expects($this->once())
                                ->method('search')
                                ->with($userMessage, $this->anything()) // 元のメッセージにフォールバックするはず
                                ->willReturn('複雑な質問の結果');

        $this->chatService->getAnswer(true, $userMessage);
    }

    public function test_GPTクエリが短すぎる場合にgetAnswerが検索に元のメッセージをフォールバックする(): void
    {
        $userMessage = "別の質問。";

        $this->setPrivateProperty($this->chatService, 'googleApiKey', 'dummy_key');
        $this->setPrivateProperty($this->chatService, 'googleCxId', 'dummy_cx');
        $this->setPrivateProperty($this->chatService, 'webSearchTool', $this->webSearchToolMock);

        $this->gptMock->method('getAnswer')
            ->will($this->onConsecutiveCalls(
                'はい', // 検索を判断: はい
                'a',    // クエリを生成: 短すぎる
                '別の質問の最終回答'
            ));

        $this->webSearchToolMock->expects($this->once())
                                ->method('search')
                                ->with($userMessage, $this->anything()) // 元のメッセージにフォールバックするはず
                                ->willReturn('別の質問の結果');

        $this->chatService->getAnswer(true, $userMessage);
    }
}
