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

    public function testJudgeCommand()
    {
        $this->assertSame(Command::AddOneTimeTrigger, $this->bot->judgeCommand("1時間後に「できたよ」と送って"));
    }
}
