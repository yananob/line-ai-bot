<?php

declare(strict_types=1);

namespace MyApp\Tests\Domain\Bot\Service; // PSR-4に合わせた名前空間 (既存)

use PHPUnit\Framework\TestCase; // 標準的なPHPUnitの名前空間
use Carbon\Carbon;
use MyApp\Command;
use MyApp\Domain\Bot\Service\CommandAndTriggerService;
use MyApp\Domain\Bot\Trigger\TimerTrigger;
use yananob\MyTools\Gpt; // モック用

final class CommandAndTriggerServiceTest extends \PHPUnit\Framework\TestCase // TestCaseの完全修飾名を使用
{
    private CommandAndTriggerService $commandAndTriggerService;
    private $gptMock;

    protected function setUp(): void
    {
        Carbon::setTestNow(new Carbon("2025/01/01T09:00:00+09:00")); // 日付に敏感なテストがあれば維持

        $this->gptMock = $this->createMock(Gpt::class);
        putenv("OPENAI_KEY_LINE_AI_BOT=test_api_key"); // 環境変数の設定
        $this->commandAndTriggerService = new CommandAndTriggerService();
        $this->setPrivateProperty($this->commandAndTriggerService, 'gpt', $this->gptMock);
    }

    // プライベートプロパティ設定用のヘルパーメソッド
    protected function setPrivateProperty($object, string $propertyName, $value): void
    {
        $reflection = new \ReflectionClass($object);
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true); // PHP8.1+ではprivateプロパティへのアクセスにsetAccessible(true)は不要な場合あり
        $property->setValue($object, $value);
    }

    /**
     * @dataProvider provideJudgeCommandCases
     */
    public function test_コマンド判定が正しいコマンドを返す(string $message, string $gptResponse, Command $expectedCommand): void
    {
        $this->gptMock->expects($this->once())
            ->method('getAnswer')
            ->with(CommandAndTriggerService::PROMPT_JUDGE_COMMAND, $message)
            ->willReturn($gptResponse);

        $actualCommand = $this->commandAndTriggerService->judgeCommand($message);
        $this->assertSame($expectedCommand, $actualCommand);
    }

    public static function provideJudgeCommandCases(): array
    {
        return [
            // message, gptResponse, expectedCommand
            ["1時間後に「できたよ」と送って", "3", Command::AddOneTimeTrigger],
            ["明後日の6時半に「おはよう」と送って", "3", Command::AddOneTimeTrigger],
            ["毎日朝6時半にモーニングメッセージを送って", "4", Command::AddDailyTrigger],
            ["お昼のメッセージを送るのをやめて", "5", Command::RemoveTrigger],
            ["何ができんの？", "8", Command::ShowHelp],
            ["未知のコマンド", "9", Command::Other],
            ["GPTが数字以外を返した場合", "unexpected_string", Command::Other], // 堅牢性テスト
            ["GPTが空文字を返した場合", "", Command::Other], // 堅牢性テスト
        ];
    }

    /**
     * @dataProvider provideGenerateOneTimeTriggerCases
     */
    public function test_単発トリガー生成が正しいタイマートリガーを返す(string $message, string $gptResponse, array $expectedTriggerData): void
    {
        $this->gptMock->expects($this->once())
            ->method('getAnswer')
            ->with(CommandAndTriggerService::PROMPT_SPLIT_ONE_TIME_TRIGGER, $message)
            ->willReturn($gptResponse);

        $trigger = $this->commandAndTriggerService->generateOneTimeTrigger($message);

        $this->assertInstanceOf(TimerTrigger::class, $trigger);
        $this->assertEquals($expectedTriggerData['date'], $trigger->getDate());
        $this->assertEquals($expectedTriggerData['time'], $trigger->getTime());
        $this->assertEquals($expectedTriggerData['request'], $trigger->getRequest());
    }

    public static function provideGenerateOneTimeTriggerCases(): array
    {
        // 注意: Carbon::setTestNow は setUp にあるため、「today」と「tomorrow」は 2025/01/01 09:00:00 相対です。
        // CommandAndTriggerService自体は日付解析にCarbonを使用せず、GPTの出力に依存します。
        // TimerTriggerのshouldRunNowはCarbonを使用しますが、それは別途テストされます。
        // ここでは、GPTの応答がTimerTriggerプロパティに正しく解析されるかどうかだけをテストします。
        return [
            [
                "1時間後に「できたよ」と送って",
                "・日付：today\n・時刻：10:00\n・依頼内容：「できたよ」と送って", // GPTはこれを出力するかもしれない
                ['date' => "today", 'time' => "10:00", 'request' => "「できたよ」と送って"]
            ],
            [
                "明日の6時半に「おはよう」と送って",
                "・日付：tomorrow\n・時刻：06:30\n・依頼内容：「おはよう」と送って",
                ['date' => "tomorrow", 'time' => "06:30", 'request' => "「おはよう」と送って"]
            ],
            [
                "2025-02-10の15:00にリマインド",
                "・日付：2025-02-10\n・時刻：15:00\n・依頼内容：リマインド",
                ['date' => "2025-02-10", 'time' => "15:00", 'request' => "リマインド"]
            ],
            [
                // 堅牢性テスト: GPTが不正な形式の出力を返す
                "不完全なメッセージ",
                "・日付：today\n・時刻：", // 時刻と依頼内容が欠落
                ['date' => "today", 'time' => "now", 'request' => "Could not parse request"] // サービスからのデフォルト値
            ],
        ];
    }

    /**
     * @dataProvider provideGenerateDailyTriggerCases
     */
    public function test_デイリートリガー生成が正しいタイマートリガーを返す(string $message, string $gptResponse, array $expectedTriggerData): void
    {
        $this->gptMock->expects($this->once())
            ->method('getAnswer')
            ->with(CommandAndTriggerService::PROMPT_SPLIT_DAILY_TRIGGER, $message)
            ->willReturn($gptResponse);

        $trigger = $this->commandAndTriggerService->generateDailyTrigger($message);

        $this->assertInstanceOf(TimerTrigger::class, $trigger);
        $this->assertEquals($expectedTriggerData['date'], $trigger->getDate());
        $this->assertEquals($expectedTriggerData['time'], $trigger->getTime());
        $this->assertEquals($expectedTriggerData['request'], $trigger->getRequest());
    }

    public static function provideGenerateDailyTriggerCases(): array
    {
        return [
            [
                "8時半に「いってらっしゃい」と送って",
                "・日付：everyday\n・時刻：08:30\n・依頼内容：「いってらっしゃい」と送って",
                ['date' => "everyday", 'time' => "08:30", 'request' => "「いってらっしゃい」と送って"]
            ],
            [
                "夜の11時半に「もう寝ましょう」と送って",
                "・日付：everyday\n・時刻：23:30\n・依頼内容：「もう寝ましょう」と送って",
                ['date' => "everyday", 'time' => "23:30", 'request' => "「もう寝ましょう」と送って"]
            ],
        ];
    }
}
