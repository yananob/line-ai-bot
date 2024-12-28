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
            ["1時間後に「できたよ」と送って", ["today", "今＋60分", "「できたよ」と送って"]],
            ["明日の6時半に「おはよう」と送って", ["tomorrow", "6:30", "「おはよう」と送って"]],
        ];
    }
    /**
     * @dataProvider provideSplitOneTimeTrigger
     */
    public function testSplitOneTimeTrigger($message, $expected)
    {
        $result = $this->bot->splitOneTimeTrigger($message);
        $this->assertSame($expected[0], $result->date);
        $this->assertSame($expected[1], $result->time);
        $this->assertSame($expected[2], $result->request);
    }
}
