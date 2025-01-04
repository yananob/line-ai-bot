<?php

declare(strict_types=1);

use Carbon\Carbon;
use MyApp\Command;
use MyApp\TimerTrigger;

final class TimerTriggerTest extends PHPUnit\Framework\TestCase
{
    const TIMER_TRIGGERED_BY_N_MINS = 30;

    protected function setUp(): void
    {
        Carbon::setTestNow(new Carbon("2025/01/01T09:00:00+09:00"));
    }

    public function provideConstructor(): array
    {
        return [
            // date, time, request, expected
            ["今日", "10:30", "「できたよ」と送って", "2025/01/01 10:30：「できたよ」と送って"],
            ["明日", "今", "「おはよう」と送って", "2025/01/02 09:00：「おはよう」と送って"],
            ["明後日", "今＋60分", "「さよなら」と送って", "2025/01/03 10:00：「さよなら」と送って"],
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
            ["今日", "08:30", "", false],
            ["今日", "08:31", "", true],
            ["今日", "09:00", "", true],
            ["今日", "09:01", "", false],
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
