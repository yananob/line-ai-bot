<?php

declare(strict_types=1);

use Carbon\Carbon;
use MyApp\Command;
use MyApp\TimerTrigger;

final class TimerTriggerTest extends PHPUnit\Framework\TestCase
{
    const TIMER_TRIGGERED_BY_N_MINS = 10;

    protected function setUp(): void
    {
        Carbon::setTestNow(new Carbon("2025/01/01T09:00:20+09:00"));
    }

    public function provideConstructor(): array
    {
        return [
            // date, time, request, expected
            ["everyday", "23:00", "「お休み」と送って", "毎日 23:00 「お休み」と送って"],
            ["today", "10:30", "「できたよ」と送って", "2025/01/01 10:30 「できたよ」と送って"],
            ["tomorrow", "now", "「おはよう」と送って", "2025/01/02 09:00 「おはよう」と送って"],
            ["day after tomorrow", "now +60 mins", "「さよなら」と送って", "2025/01/03 10:00 「さよなら」と送って"],
            ["2025/01/04", "now", "「こんにちは」と送って", "2025/01/04 09:00 「こんにちは」と送って"],
        ];
    }
    /**
     * @dataProvider provideConstructor
     */
    public function testConstructor($date, $time, $request, $expected)
    {
        $trigger = new TimerTrigger($date, $time, $request);
        $this->assertSame($expected, "{$trigger}");
    }

    public function provideShouldRunNow(): array
    {
        return [
            // date, time, _, expected
            ["today", "08:59", "", false],
            ["today", "09:00", "", false],
            ["today", "09:09", "", true],
            ["today", "09:10", "", true],
            ["today", "09:11", "", false],
            ["2025/01/01", "09:00", "", false],
            ["2025/01/01", "09:01", "", true],
            ["2025/01/02", "09:01", "", false],
        ];
    }
    /**
     * @dataProvider provideShouldRunNow
     */
    public function testShouldRunNow($date, $time, $request, $expected)
    {
        $trigger = new TimerTrigger($date, $time, $request);
        $this->assertSame($expected, $trigger->shouldRunNow(self::TIMER_TRIGGERED_BY_N_MINS));
    }
}
