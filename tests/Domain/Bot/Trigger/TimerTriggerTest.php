<?php

declare(strict_types=1);

namespace MyApp\Tests\Domain\Bot\Trigger; // PSR-4のための調整済み名前空間 (既存)

use PHPUnit\Framework\TestCase;
use MyApp\Domain\Bot\Trigger\TimerTrigger;
use Carbon\Carbon;
use MyApp\Consts;

final class TimerTriggerTest extends \PHPUnit\Framework\TestCase // TestCaseの完全修飾名を使用
{
    private const TEST_DATE = '2024/01/01'; // テスト日
    private const TRIGGER_DURATION_MINS = 10; // トリガーの持続時間 (分)

    public function test_toStringが正しくフォーマットされる(): void
    {
        $trigger = new TimerTrigger("2023-12-25", "10:00", "テストリクエスト");
        $this->assertEquals("2023-12-25 10:00 テストリクエスト", (string)$trigger);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow(); // 各テスト後にCarbonをリセットして実時間を使用するようにする
        parent::tearDown();
    }

    // --- データプロバイダー ---

    public static function provide_毎日トリガーのデータ(): array
    {
        return [
            // モックする現在時刻, 期待される結果, メッセージ
            ['2023-01-01 10:00:00', true, '毎日: 正確な時刻に実行されるべき'],
            ['2023-01-01 10:05:00', true, '毎日: 間隔内に実行されるべき'],
            ['2023-01-01 10:09:59', true, '毎日: 間隔の終わりに実行されるべき'],
            ['2023-01-01 10:10:00', false, '毎日: 間隔外では実行されないべき (スケジュールされた時刻がスロット終了より前ではない)'],
            ['2023-01-01 09:59:59', false, '毎日: 時刻より前には実行されないべき (スケジュールされた時刻がスロット [09:50, 10:00) にない)']
        ];
    }

    public static function provide_今日トリガーのデータ(): array
    {
        return [
            // モックする現在時刻, 期待される結果, メッセージ
            // コンストラクタは "2023-05-10 12:00:00" に設定され、トリガーは "今日" の "14:30"
            // これらのテストのタイマー間隔は5分
            ['2023-05-10 14:30:00', true, '今日: 正確な時刻に実行されるべき'], // スロット [14:30, 14:35), スケジュール 14:30. OK.
            ['2023-05-10 14:34:59', true, '今日: 間隔の終わりに実行されるべき'], // スロット [14:30, 14:35), スケジュール 14:30. OK.
            ['2023-05-10 14:35:00', false, '今日: 間隔外では実行されないべき'], // スロット [14:35, 14:40), スケジュール 14:30. スロットにない.
            ['2023-05-11 14:30:00', false, '今日: 異なる日には実行されないべき'] // トリガーの実際の日付は 2023-05-10.
        ];
    }

    public static function provide_特定日トリガーのデータ(): array
    {
        return [
            // モックする現在時刻, 期待される結果, メッセージ
            // トリガーは "2023-06-15" の "08:00". タイマー間隔は15分.
            ['2023-06-15 08:00:00', true, '特定日: 正確な時刻に実行されるべき'], // スロット [08:00, 08:15), スケジュール 08:00. OK.
            ['2023-06-15 08:14:59', true, '特定日: 間隔の終わりに実行されるべき'], // スロット [08:00, 08:15), スケジュール 08:00. OK.
            ['2023-06-15 08:15:00', false, '特定日: 間隔外では実行されないべき'], // スロット [08:15, 08:30), スケジュール 08:00. スロットにない.
            ['2023-06-14 08:00:00', false, '特定日: 異なる日には実行されないべき'] // トリガーの実際の日付は 2023-06-15.
        ];
    }

    public static function provide_汎用shouldRunNowのデータ(): array
    {
        // スケジュールされた時刻文字列, 現在時刻文字列, 期待される結果, メッセージ
        // これらのテストはすべて、トリガーの日付として self::TEST_DATE ('2024/01/01') を使用します。
        // これらのテストはすべて、間隔として self::TRIGGER_DURATION_MINS (10) を使用します。
        return [
            ['07:30', '07:20:00', false, '汎用: 07:30 スケジュール, 07:20 現在. スロット [07:20,07:30). スケジュール 07:30 は 07:30 より前ではない. -> F'],
            ['07:30', '07:30:00', true,  '汎用: 07:30 スケジュール, 07:30 現在. スロット [07:30,07:40). スケジュール 07:30 はスロット内. -> T'],
            ['07:30', '07:35:00', true,  '汎用: 07:30 スケジュール, 07:35 現在. スロット [07:30,07:40). スケジュール 07:30 はスロット内. -> T'],
            ['07:30', '07:29:59', false, '汎用: 07:30 スケジュール, 07:29:59 現在. スロット [07:20,07:30). スケジュール 07:30 は 07:30 より前ではない. -> F'],
            ['07:30', '07:39:00', true,  '汎用: 07:30 スケジュール, 07:39 現在. スロット [07:30,07:40). スケジュール 07:30 はスロット内. -> T'],
            ['07:30', '07:40:00', false, '汎用: 07:30 スケジュール, 07:40 現在. スロット [07:40,07:50). スケジュール 07:30 はスロットにない. -> F'],
            ['07:30', '06:00:00', false, '汎用: 07:30 スケジュール, 06:00 現在. スロット [06:00,06:10). スケジュール 07:30 はスロットにない. -> F'],
            ['07:30', '07:25:00', false, '汎用: 07:30 スケジュール, 07:25 現在. スロット [07:20,07:30). スケジュール 07:30 は 07:30 より前ではない. -> F'],
            ['07:25', '07:30:00', false, '汎用: 07:25 スケジュール, 07:30 現在. スロット [07:30,07:40). スケジュール 07:25 はスロットにない. -> F'],
            ['07:00', '07:00:00', true,  '汎用: 07:00 スケジュール, 07:00 現在. スロット [07:00,07:10). スケジュール 07:00 はスロット内. -> T'],
            ['07:00', '07:09:00', true,  '汎用: 07:00 スケジュール, 07:09 現在. スロット [07:00,07:10). スケジュール 07:00 はスロット内. -> T'],
            ['07:00', '07:10:00', false, '汎用: 07:00 スケジュール, 07:10 現在. スロット [07:10,07:20). スケジュール 07:00 はスロットにない. -> F'],
            ['07:30:00', '07:21:10', false, '汎用: 07:30:00 スケジュール, 07:21:10 現在. スロット [07:20,07:30). スケジュール 07:30 は 07:30 より前ではない. -> F']
        ];
    }

    // --- データプロバイダーを使用した新しいテストメソッド ---

    /**
     * @dataProvider provide_毎日トリガーのデータ
     */
    public function test_毎日トリガーのshouldRunNow(string $currentTimeToMock, bool $expectedResult, string $message): void
    {
        $timeZone = new \DateTimeZone(Consts::TIMEZONE);
        Carbon::setTestNow(Carbon::parse($currentTimeToMock, $timeZone));
        $trigger = new TimerTrigger("everyday", "10:00", "テスト everyday 10:00"); // スケジュール時刻は10:00
        // 注意: これらの元のテストの間隔は10分でした。
        $this->assertSame($expectedResult, $trigger->shouldRunNow(10), $message);
    }

    /**
     * @dataProvider provide_今日トリガーのデータ
     */
    public function test_今日トリガーのshouldRunNow(string $currentTimeToMock, bool $expectedResult, string $message): void
    {
        $timeZone = new \DateTimeZone(Consts::TIMEZONE);
        // このテストのコンテキストのために固定の「今日」を設定 (コンストラクタのCarbon::now()経由)
        Carbon::setTestNow(Carbon::parse("2023-05-10 12:00:00", $timeZone));
        $trigger = new TimerTrigger("today", "14:30", "テスト today 14:30"); // スケジュール時刻は2023-05-10の14:30

        // さて、特定のアサーションのために「現在時刻」を設定
        Carbon::setTestNow(Carbon::parse($currentTimeToMock, $timeZone));
        // 注意: これらの元のテストの間隔は5分でした。
        $this->assertSame($expectedResult, $trigger->shouldRunNow(5), $message);
    }

    /**
     * @dataProvider provide_特定日トリガーのデータ
     */
    public function test_特定日トリガーのshouldRunNow(string $currentTimeToMock, bool $expectedResult, string $message): void
    {
        $timeZone = new \DateTimeZone(Consts::TIMEZONE);
        Carbon::setTestNow(Carbon::parse($currentTimeToMock, $timeZone));
        // 特定日トリガーの場合、日付が絶対ならコンストラクタのCarbon::now()は$this->actualDateに影響しません。
        $trigger = new TimerTrigger("2023-06-15", "08:00", "テスト特定日 08:00"); // 2023-06-15 08:00にスケジュール

        // 注意: これらの元のテストの間隔は15分でした。
        $this->assertSame($expectedResult, $trigger->shouldRunNow(15), $message);
    }

    /**
     * @dataProvider provide_汎用shouldRunNowのデータ
     */
    public function test_汎用shouldRunNowシナリオ(string $scheduledTimeForTrigger, string $currentTimeForCheck, bool $expectedResult, string $assertionMessage): void
    {
        $timeZone = new \DateTimeZone(Consts::TIMEZONE);

        // TimerTriggerが 'today' を使用する場合のコンストラクタの基準日を設定
        // これにより、$trigger->actualDate が self::TEST_DATE になります
        Carbon::setTestNow(Carbon::parse(self::TEST_DATE . ' 00:00:00', $timeZone));
        $trigger = new TimerTrigger('today', $scheduledTimeForTrigger, 'テスト汎用 ' . $scheduledTimeForTrigger);

        // shouldRunNowチェックの現在時刻を設定
        $now = Carbon::parse(self::TEST_DATE . ' ' . $currentTimeForCheck, $timeZone);
        Carbon::setTestNow($now);

        $this->assertSame($expectedResult, $trigger->shouldRunNow(self::TRIGGER_DURATION_MINS), $assertionMessage);
    }

    // --- 保持されているテストメソッド ---

    public function test_shouldRunNowが明日を正しく処理する(): void
    {
        $timeZone = new \DateTimeZone(Consts::TIMEZONE);

        // 現在の「日」を2023-07-01に設定。したがって、トリガーロジックの「明日」は2023-07-02になります。
        Carbon::setTestNow(Carbon::parse("2023-07-01 10:00:00", $timeZone));
        $trigger = new TimerTrigger("tomorrow", "11:00", "テスト");

        // 実際の「明日」(2023-07-02)のテスト時刻
        $testTimeOnActualTomorrow = Carbon::parse("2023-07-02 11:00:00", $timeZone);
        Carbon::withTestNow($testTimeOnActualTomorrow, function() use ($trigger) { // この特定の「now」を分離するためにwithTestNowを使用
            // この元のテストの間隔は5でした
            $this->assertTrue($trigger->shouldRunNow(5), "実際の「明日」に設定された時刻に実行されるべきです");
        });

        // 「今日」(2023-07-01)のテスト時刻 (実行されないべき)
        Carbon::setTestNow(Carbon::parse("2023-07-01 11:00:00", $timeZone));
        $this->assertFalse($trigger->shouldRunNow(5), "日付が「明日」の場合、「今日」には実行されないべきです");
    }

    public function test_shouldRunNowが無効な時刻フォーマットの場合にFalseを返す(): void
    {
        $trigger = new TimerTrigger("everyday", "無効な時刻", "テスト");
        Carbon::setTestNow(Carbon::parse("2023-01-01 10:00:00", new \DateTimeZone(Consts::TIMEZONE)));
        $this->assertFalse($trigger->shouldRunNow(10));
    }

    public function test_shouldRunNowがコンストラクタ処理後にnowプラスX分を正しく処理する(): void
    {
        $timezone = new \DateTimeZone(Consts::TIMEZONE);

        Carbon::setTestNow(Carbon::create(2023, 1, 1, 10, 0, 0, $timezone));
        $trigger = new TimerTrigger("today", "now +30 mins", "テスト 'now +30 mins'");
        $this->assertEquals('10:30', $trigger->getTime(), "時刻は10:30に計算されるべきです");
        $this->assertEquals(Carbon::create(2023, 1, 1, 0, 0, 0, $timezone)->format('Y/m/d'), $trigger->getActualDate(), "ActualDateは2023/01/01であるべきです");

        $duration = 5; // 間隔

        Carbon::setTestNow(Carbon::create(2023, 1, 1, 10, 30, 0, $timezone));
        $this->assertTrue($trigger->shouldRunNow($duration), "計算された時刻(10:30)に実行されるべきです");

        Carbon::setTestNow(Carbon::create(2023, 1, 1, 10, 32, 0, $timezone));
        $this->assertTrue($trigger->shouldRunNow($duration), "計算された時刻の間隔内(10:32)に実行されるべきです");

        Carbon::setTestNow(Carbon::create(2023, 1, 1, 10, 34, 59, $timezone));
        $this->assertTrue($trigger->shouldRunNow($duration), "計算された時刻の間隔の終わり(10:34:59)に実行されるべきです");

        Carbon::setTestNow(Carbon::create(2023, 1, 1, 10, 35, 0, $timezone));
        $this->assertFalse($trigger->shouldRunNow($duration), "計算された時刻の間隔外(10:35)では実行されないべきです");

        Carbon::setTestNow(Carbon::create(2023, 1, 1, 10, 0, 0, $timezone));
        $this->assertFalse($trigger->shouldRunNow($duration), "元の「now」の時刻(10:00)では実行されないべきです、ターゲットは10:30なので");

        Carbon::setTestNow(Carbon::create(2023, 1, 1, 11, 0, 0, $timezone));
        $this->assertFalse($trigger->shouldRunNow($duration), "「now」がウィンドウを大幅に過ぎている場合(11:00)は実行されないべきです");

        Carbon::setTestNow(Carbon::create(2024, 5, 10, 15, 0, 0, $timezone));
        $everydayTrigger = new TimerTrigger("everyday", "now +10 mins", "テスト everyday now +10");
        $this->assertEquals("15:10", $everydayTrigger->getTime());

        Carbon::setTestNow(Carbon::create(2024, 5, 10, 15, 10, 0, $timezone));
        $this->assertTrue($everydayTrigger->shouldRunNow($duration));

        Carbon::setTestNow(Carbon::create(2024, 5, 11, 15, 10, 0, $timezone));
        $this->assertTrue($everydayTrigger->shouldRunNow($duration), "Everydayトリガーは後続の日にも計算された時刻に実行されるべきです");

        Carbon::setTestNow(Carbon::create(2024, 5, 10, 15, 0, 0, $timezone));
        $this->assertFalse($everydayTrigger->shouldRunNow($duration));

        Carbon::setTestNow(Carbon::create(2024, 5, 10, 15, 15, 0, $timezone));
        $this->assertFalse($everydayTrigger->shouldRunNow($duration));

        Carbon::setTestNow();
    }
}
