<?php

declare(strict_types=1);

namespace MyApp\Tests\Domain\Bot\ValueObject;

use PHPUnit\Framework\TestCase;
use MyApp\Domain\Bot\ValueObject\TriggerSchedule;
use Carbon\Carbon;
use MyApp\Domain\Bot\Consts;

final class TriggerScheduleTest extends TestCase
{
    protected function setUp(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-01-01 10:00:00', new \DateTimeZone(Consts::TIMEZONE)));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
    }

    public function test_日付の解決_today(): void
    {
        $schedule = new TriggerSchedule('today', '12:00');
        $this->assertEquals('2025/01/01', $schedule->getResolvedDate());
        $this->assertEquals('today', $schedule->getOriginalDate());
    }

    public function test_日付の解決_tomorrow(): void
    {
        $schedule = new TriggerSchedule('tomorrow', '12:00');
        $this->assertEquals('2025/01/02', $schedule->getResolvedDate());
        $this->assertEquals('tomorrow', $schedule->getOriginalDate());
    }

    public function test_日付の解決_day_after_tomorrow(): void
    {
        $schedule = new TriggerSchedule('day after tomorrow', '12:00');
        $this->assertEquals('2025/01/03', $schedule->getResolvedDate());
        $this->assertEquals('day after tomorrow', $schedule->getOriginalDate());
    }

    public function test_日付の解決_everyday(): void
    {
        $schedule = new TriggerSchedule('everyday', '12:00');
        $this->assertEquals('everyday', $schedule->getResolvedDate());
    }

    public function test_日付の解決_特定の日付(): void
    {
        $schedule = new TriggerSchedule('2025-12-31', '23:59');
        $this->assertEquals('2025/12/31', $schedule->getResolvedDate());
    }

    public function test_日付の解決_不正な形式(): void
    {
        // Carbon::parse が失敗した場合、オリジナルが返る
        $schedule = new TriggerSchedule('invalid-date', '12:00');
        $this->assertEquals('invalid-date', $schedule->getResolvedDate());
    }

    public function test_時刻の解決_now_plus_X_mins(): void
    {
        // 現在 10:00
        $schedule = new TriggerSchedule('today', 'now +15 mins');
        $this->assertEquals('10:15', $schedule->getResolvedTime());
        $this->assertEquals('now +15 mins', $schedule->getOriginalTime());
    }

    public function test_時刻の解決_通常の時刻(): void
    {
        $schedule = new TriggerSchedule('today', '08:30');
        $this->assertEquals('08:30', $schedule->getResolvedTime());
    }

    /**
     * @dataProvider provideShouldRunNowData
     */
    public function test_shouldRunNow(
        string $date,
        string $time,
        string $now,
        int $interval,
        bool $expected
    ): void {
        $tz = new \DateTimeZone(Consts::TIMEZONE);
        // コンストラクタ呼び出し時の「現在時刻」を設定（相対日付解決のため）
        Carbon::setTestNow(Carbon::parse('2025-01-01 10:00:00', $tz));
        $schedule = new TriggerSchedule($date, $time);

        // 判定時の「現在時刻」を設定
        Carbon::setTestNow(Carbon::parse($now, $tz));

        $this->assertSame($expected, $schedule->shouldRunNow($interval));
    }

    public static function provideShouldRunNowData(): array
    {
        return [
            // $date, $time, $now, $interval, $expected
            'ジャスト時刻' => ['today', '12:00', '2025-01-01 12:00:00', 10, true],
            'スロット内' => ['today', '12:00', '2025-01-01 12:05:00', 10, true],
            'スロット終了直前' => ['today', '12:00', '2025-01-01 12:09:59', 10, true],
            'スロット終了' => ['today', '12:00', '2025-01-01 12:10:00', 10, false],
            'スロット開始直前' => ['today', '12:00', '2025-01-01 11:59:59', 10, false],
            '別の日' => ['today', '12:00', '2025-01-02 12:00:00', 10, false],
            '毎日_当日' => ['everyday', '07:00', '2025-01-01 07:00:00', 30, true],
            '毎日_翌日' => ['everyday', '07:00', '2025-01-02 07:05:00', 30, true],
            '相対時刻_解決済み' => ['today', 'now +10 mins', '2025-01-01 10:10:00', 5, true],
            '不正な時刻形式' => ['today', 'invalid', '2025-01-01 10:00:00', 10, false],
        ];
    }
}
