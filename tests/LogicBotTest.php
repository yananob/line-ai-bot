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
            ["6時半に「おはよう」と送って", Command::AddOneTimeTrigger],
        ];
    }

    /**
     * @dataProvider provideJudgeCommand
     */
    public function testJudgeCommand($message, $expected)
    {
        $this->assertSame($expected, $this->bot->judgeCommand($message));
    }
}
