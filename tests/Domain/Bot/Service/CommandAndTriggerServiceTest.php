<?php

declare(strict_types=1);

namespace MyApp\Tests\Domain\Bot\Service; // Adjusted namespace for PSR-4

use PHPUnit\Framework\TestCase; // Standard PHPUnit namespace
use Carbon\Carbon;
use MyApp\Command;
use MyApp\Domain\Bot\Service\CommandAndTriggerService;
use MyApp\Domain\Bot\Trigger\TimerTrigger;
use yananob\MyTools\Gpt; // For mocking

final class CommandAndTriggerServiceTest extends TestCase
{
    private CommandAndTriggerService $commandAndTriggerService;
    private $gptMock;

    protected function setUp(): void
    {
        Carbon::setTestNow(new Carbon("2025/01/01T09:00:00+09:00")); // Keep for date-sensitive tests if any

        $this->gptMock = $this->createMock(Gpt::class);
        $this->commandAndTriggerService = new CommandAndTriggerService();
        $this->setPrivateProperty($this->commandAndTriggerService, 'gpt', $this->gptMock);
    }

    protected function setPrivateProperty($object, string $propertyName, $value): void
    {
        $reflection = new \ReflectionClass($object);
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);
        $property->setValue($object, $value);
    }

    /**
     * @dataProvider provideJudgeCommandCases
     */
    public function testJudgeCommandReturnsCorrectCommand(string $message, string $gptResponse, Command $expectedCommand): void
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
            ["教師口調になって", "1", Command::ChangeAnswerStyle], // Assuming "1" maps to ChangeAnswerStyle
            ["学校の教師になって", "2", Command::ChangeBotCharacteristics], // Assuming "2" maps to ChangeBotCharacteristics
            ["1時間後に「できたよ」と送って", "3", Command::AddOneTimeTrigger],
            ["明後日の6時半に「おはよう」と送って", "3", Command::AddOneTimeTrigger],
            ["毎日朝6時半にモーニングメッセージを送って", "4", Command::AddDaiyTrigger],
            ["お昼のメッセージを送るのをやめて", "5", Command::RemoveTrigger],
            ["何ができんの？", "8", Command::ShowHelp],
            ["未知のコマンド", "9", Command::Other],
            ["GPTが数字以外を返した場合", "unexpected_string", Command::Other], // Test robustness
            ["GPTが空文字を返した場合", "", Command::Other], // Test robustness
        ];
    }

    /**
     * @dataProvider provideGenerateOneTimeTriggerCases
     */
    public function testGenerateOneTimeTriggerReturnsCorrectTimerTrigger(string $message, string $gptResponse, array $expectedTriggerData): void
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
        // Note: Carbon::setTestNow is in setUp, so 'today' and 'tomorrow' are relative to 2025/01/01 09:00:00
        // The CommandAndTriggerService itself doesn't use Carbon for date parsing, it relies on GPT output.
        // The TimerTrigger's shouldRunNow uses Carbon, but that's tested separately.
        // Here we just test if the GPT response is correctly parsed into TimerTrigger properties.
        return [
            [
                "1時間後に「できたよ」と送って",
                "・日付：today\n・時刻：10:00\n・依頼内容：「できたよ」と送って", // GPT might output this
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
            [   // Test robustness: GPT returns malformed output
                "不完全なメッセージ",
                "・日付：today\n・時刻：", // Missing time and request
                ['date' => "today", 'time' => "now", 'request' => "Could not parse request"] // Default values from service
            ],
        ];
    }

    /**
     * @dataProvider provideGenerateDailyTriggerCases
     */
    public function testGenerateDailyTriggerReturnsCorrectTimerTrigger(string $message, string $gptResponse, array $expectedTriggerData): void
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
