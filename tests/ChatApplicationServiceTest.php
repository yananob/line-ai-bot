<?php

declare(strict_types=1);

namespace MyApp\Tests\Application; // 名前空間を追加

use Carbon\Carbon;
use yananob\MyTools\Gpt; // モック用

use MyApp\Application\ChatApplicationService;
use MyApp\Domain\Bot\BotRepository;
use MyApp\Domain\Conversation\ConversationRepository;
use MyApp\Domain\Bot\Bot;
use MyApp\Domain\Conversation\Conversation; // 会話データのモック用

// PHPUnit\Framework\TestCase は通常、グローバルにまたはオートローダー経由で利用可能

final class ChatApplicationServiceTest extends \PHPUnit\Framework\TestCase // クラス宣言を更新
{
    private ChatApplicationService $chatService; // $bot からリネームされました

    private $botRepositoryMock;
    private $conversationRepositoryMock;
    private $gptMock;

    const TARGET_ID_AUTOTEST = "TARGET_ID_AUTOTEST";
    const TARGET_ID_FOR_DEFAULT_BEHAVIOR = "TARGET_ID_FOR_DEFAULT_BEHAVIOR"; // 最小/デフォルト設定を持つボット用
    const TARGET_ID_THAT_THROWS_EXCEPTION = "TARGET_ID_THAT_THROWS_EXCEPTION"; // コンストラクタ失敗テスト用

    protected function setUp(): void
    {
        // putenv('OPENAI_KEY_LINE_AI_BOT=dummy_key');
        $this->botRepositoryMock = $this->createMock(BotRepository::class);
        $this->conversationRepositoryMock = $this->createMock(ConversationRepository::class);
        $this->gptMock = $this->createMock(Gpt::class);

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
            $this->conversationRepositoryMock
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

    public function test_AutotestボットのLineTargetを取得する(): void
    {
        // chatService は TARGET_ID_AUTOTEST です
        $this->assertSame('test', $this->chatService->getLineTarget());
    }

    public function test_DefaultBehaviorボットのLineTargetを取得する(): void
    {
        $chatServiceDefault = new ChatApplicationService(
            self::TARGET_ID_FOR_DEFAULT_BEHAVIOR,
            $this->botRepositoryMock,
            $this->conversationRepositoryMock
        );
        $this->assertSame('test', $chatServiceDefault->getLineTarget());
    }
}
