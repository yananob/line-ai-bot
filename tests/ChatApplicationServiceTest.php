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
        $this->botRepositoryMock = $this->createMock(BotRepository::class);
        $this->conversationRepositoryMock = $this->createMock(ConversationRepository::class);
        $this->gptMock = $this->createMock(Gpt::class);

        // --- TARGET_ID_AUTOTEST 用のボット (フル機能ボット) ---
        $botAutotest = new Bot(self::TARGET_ID_AUTOTEST);
        $botAutotest->setLineTarget('test_line_target_autotest');
        $botAutotest->setBotCharacteristics(['Bot char 1 AUTOTEST']);
        $botAutotest->setHumanCharacteristics(['Human char 1 AUTOTEST']);
        $botAutotest->setConfigRequests(['Default request AUTOTEST']);

        // --- TARGET_ID_FOR_DEFAULT_BEHAVIOR 用のボット (最小/デフォルト風設定ボット) ---
        $botDefaultBehavior = new Bot(self::TARGET_ID_FOR_DEFAULT_BEHAVIOR);
        $botDefaultBehavior->setLineTarget('test_line_target_default');
        $botDefaultBehavior->setBotCharacteristics(['Default Bot char BEHAVIOR']);
        $botDefaultBehavior->setHumanCharacteristics([]);
        $botDefaultBehavior->setConfigRequests(['Basic request BEHAVIOR']);

        $this->botRepositoryMock->method('findById')
            ->willReturnMap([
                [self::TARGET_ID_AUTOTEST, $botAutotest],
                [self::TARGET_ID_FOR_DEFAULT_BEHAVIOR, $botDefaultBehavior],
                [self::TARGET_ID_THAT_THROWS_EXCEPTION, null],
            ]);
        $this->botRepositoryMock->method('findDefault')->willReturn($botDefaultBehavior);

        // ほとんどのテスト用のメインインスタンス
        $this->chatService = new ChatApplicationService(
            self::TARGET_ID_AUTOTEST,
            $this->botRepositoryMock,
            $this->conversationRepositoryMock,
            $this->gptMock
        );
    }

    public function test_最近の会話なしで回答を取得する(): void
    {
        $this->gptMock->method('getAnswer')->willReturn('モックされた回答');
        $this->conversationRepositoryMock->method('findByBotId')->willReturn([]);

        $answer = $this->chatService->getAnswer(
            false, // applyRecentConversations
            "今年のクリスマスは何月何日でしょうか？\n昨年のクリスマスとは違うのでしょうか？"
        );
        $this->assertSame('モックされた回答', $answer);
    }

    public function test_最近の会話ありで回答を取得する(): void
    {
        $conversation = new Conversation(self::TARGET_ID_AUTOTEST, "human", "こんにちは");
        $this->conversationRepositoryMock->method('findByBotId')
            ->willReturn([$conversation]);
        $this->gptMock->method('getAnswer')->willReturn('モックされた回答');

        $answer = $this->chatService->getAnswer(
            true, // applyRecentConversations
            "今年のクリスマスは何月何日でしょうか？\n昨年のクリスマスとは違うのでしょうか？"
        );
        $this->assertSame('モックされた回答', $answer);
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
            $this->conversationRepositoryMock,
            $this->gptMock
        );
        $this->assertSame('test', $chatServiceDefault->getLineTarget());
    }

    public function test_会話を保存する(): void
    {
        $this->conversationRepositoryMock->expects($this->exactly(2))
            ->method('save')
            ->with($this->isInstanceOf(Conversation::class));

        $this->chatService->storeConversations("ユーザーメッセージ", "ボットの回答");
    }

    public function test_トリガーを取得する(): void
    {
        $triggers = $this->chatService->getTriggers();
        $this->assertIsArray($triggers);
    }

    public function test_タイマートリガーを追加する(): void
    {
        $trigger = new \MyApp\Domain\Bot\Trigger\TimerTrigger("today", "12:00", "テストリクエスト");

        $this->botRepositoryMock->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(Bot::class));

        $triggerId = $this->chatService->addTimerTrigger($trigger);
        $this->assertNotEmpty($triggerId);
    }

    public function test_トリガーを削除する(): void
    {
        $trigger = new \MyApp\Domain\Bot\Trigger\TimerTrigger("today", "12:00", "テストリクエスト");
        $triggerId = $this->chatService->addTimerTrigger($trigger);

        $this->botRepositoryMock->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(Bot::class));

        $this->chatService->deleteTrigger($triggerId);
        $this->assertArrayNotHasKey($triggerId, $this->chatService->getTriggers());
    }
}
