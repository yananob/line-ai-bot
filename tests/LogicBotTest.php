<?php

declare(strict_types=1);

use Carbon\Carbon;
use MyApp\Command;
use MyApp\LogicBot;

final class LogicBotTest extends PHPUnit\Framework\TestCase
{
    private LogicBot $bot;

    protected function setUp(): void
    {
        Carbon::setTestNow(new Carbon("2025/01/01T09:00:00+09:00"));
        $this->bot = new LogicBot();
    }

    public function provideJudgeCommand(): array
    {
        return [
            // message, expected
            ["1時間後に「できたよ」と送って", Command::AddOneTimeTrigger],
            ["明後日の6時半に「おはよう」と送って", Command::AddOneTimeTrigger],
            ["毎日朝6時半にモーニングメッセージを送って", Command::AddDaiyTrigger],
        ];
    }

    /**
     * @dataProvider provideJudgeCommand
     */
    public function testJudgeCommand($message, $expected)
    {
        $this->assertSame($expected, $this->bot->judgeCommand($message));
    }

    public function provideSplitOneTimeTrigger(): array
    {
        return [
            // message, expected [date, time, request]
            ["1時間後に「できたよ」と送って", ["2025/01/01", "10:00", "「できたよ」と送って"]],
            ["明日の6時半に「おはよう」と送って", ["2025/01/02", "06:30", "「おはよう」と送って"]],
        ];
    }
    /**
     * @dataProvider provideSplitOneTimeTrigger
     */
    public function testSplitOneTimeTrigger($message, $expected)
    {
        $result = $this->bot->generateOneTimeTrigger($message);
        $this->assertSame($expected[0], $result->getDate());
        $this->assertSame($expected[1], $result->getTime());
        $this->assertSame($expected[2], $result->getRequest());
    }

    public function provideSplitDailyTrigger(): array
    {
        return [
            // message, expected [date, time, request]
            ["8時半に「いってらっしゃい」と送って", ["毎日", "08:30", "「いってらっしゃい」と送って"]],
            ["夜の11時半に「もう寝ましょう」と送って", ["毎日", "23:30", "「もう寝ましょう」と送って"]],
        ];
    }
    /**
     * @dataProvider provideSplitDailyTrigger
     */
    public function testSplitDailyTrigger($message, $expected)
    {
        $result = $this->bot->generateDailyTrigger($message);
        $this->assertSame($expected[0], $result->getDate());
        $this->assertSame($expected[1], $result->getTime());
        $this->assertSame($expected[2], $result->getRequest());
    }
}
