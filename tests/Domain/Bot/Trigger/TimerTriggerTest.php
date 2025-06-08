<?php

declare(strict_types=1);

namespace MyApp\Tests\Domain\Bot\Trigger; // Adjusted namespace for PSR-4

use PHPUnit\Framework\TestCase;
use MyApp\Domain\Bot\Trigger\TimerTrigger;
use Carbon\Carbon;
use MyApp\Consts;

final class TimerTriggerTest extends TestCase
{
    private const TEST_DATE = '2024/01/01';
    private const TRIGGER_DURATION_MINS = 10;

    public function testToStringFormatsCorrectly(): void
    {
        $trigger = new TimerTrigger("2023-12-25", "10:00", "Test Request");
        $this->assertEquals("2023-12-25 10:00: Test Request", (string)$trigger);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow(); // Reset Carbon to use real time after each test
        parent::tearDown();
    }

    // --- Data Providers ---

    public static function everydayTriggerDataProvider(): array
    {
        return [
            // currentTimeToMock, expectedResult, message
            ['2023-01-01 10:00:00', true, 'Everyday: Should run at exact time'],
            ['2023-01-01 10:05:00', true, 'Everyday: Should run within interval'],
            ['2023-01-01 10:09:59', true, 'Everyday: Should run at end of interval'],
            ['2023-01-01 10:10:00', false, 'Everyday: Should not run outside interval (scheduled time is not < slot end)'],
            ['2023-01-01 09:59:59', false, 'Everyday: Should not run before time (scheduled time is not in slot [09:50, 10:00))']
        ];
    }

    public static function todayTriggerDataProvider(): array
    {
        return [
            // currentTimeToMock, expectedResult, message
            // Constructor will be set to "2023-05-10 12:00:00", trigger for "today" at "14:30"
            // Timer interval for these tests is 5 mins
            ['2023-05-10 14:30:00', true, 'Today: Should run at exact time'], // Slot [14:30, 14:35), Sched 14:30. OK.
            ['2023-05-10 14:34:59', true, 'Today: Should run at end of interval'], // Slot [14:30, 14:35), Sched 14:30. OK.
            ['2023-05-10 14:35:00', false, 'Today: Should not run outside interval'], // Slot [14:35, 14:40), Sched 14:30. Not in slot.
            ['2023-05-11 14:30:00', false, 'Today: Should not run on different day'] // Actual date for trigger is 2023-05-10.
        ];
    }

    public static function specificDateTriggerDataProvider(): array
    {
        return [
            // currentTimeToMock, expectedResult, message
            // Trigger for "2023-06-15" at "08:00". Timer interval 15 mins.
            ['2023-06-15 08:00:00', true, 'Specific Date: Should run at exact time'], // Slot [08:00, 08:15), Sched 08:00. OK.
            ['2023-06-15 08:14:59', true, 'Specific Date: Should run at end of interval'], // Slot [08:00, 08:15), Sched 08:00. OK.
            ['2023-06-15 08:15:00', false, 'Specific Date: Should not run outside interval'], // Slot [08:15, 08:30), Sched 08:00. Not in slot.
            ['2023-06-14 08:00:00', false, 'Specific Date: Should not run on different day'] // Actual date for trigger is 2023-06-15.
        ];
    }

    public static function generalShouldRunNowDataProvider(): array
    {
        // scheduledTimeStr, currentTimeStr, expectedResult, message
        // All these tests use self::TEST_DATE ('2024/01/01') as the date for the trigger.
        // All these tests use self::TRIGGER_DURATION_MINS (10) as the interval.
        return [
            ['07:30', '07:20:00', false, 'General: 07:30 sched, 07:20 current. Slot [07:20,07:30). Sched 07:30 not < 07:30. -> F'],
            ['07:30', '07:30:00', true,  'General: 07:30 sched, 07:30 current. Slot [07:30,07:40). Sched 07:30 is in slot. -> T'],
            ['07:30', '07:35:00', true,  'General: 07:30 sched, 07:35 current. Slot [07:30,07:40). Sched 07:30 is in slot. -> T'],
            ['07:30', '07:29:59', false, 'General: 07:30 sched, 07:29:59 current. Slot [07:20,07:30). Sched 07:30 not < 07:30. -> F'],
            ['07:30', '07:39:00', true,  'General: 07:30 sched, 07:39 current. Slot [07:30,07:40). Sched 07:30 is in slot. -> T'],
            ['07:30', '07:40:00', false, 'General: 07:30 sched, 07:40 current. Slot [07:40,07:50). Sched 07:30 not in slot. -> F'],
            ['07:30', '06:00:00', false, 'General: 07:30 sched, 06:00 current. Slot [06:00,06:10). Sched 07:30 not in slot. -> F'],
            ['07:30', '07:25:00', false, 'General: 07:30 sched, 07:25 current. Slot [07:20,07:30). Sched 07:30 not < 07:30. -> F'],
            ['07:25', '07:30:00', false, 'General: 07:25 sched, 07:30 current. Slot [07:30,07:40). Sched 07:25 not in slot. -> F'],
            ['07:00', '07:00:00', true,  'General: 07:00 sched, 07:00 current. Slot [07:00,07:10). Sched 07:00 is in slot. -> T'],
            ['07:00', '07:09:00', true,  'General: 07:00 sched, 07:09 current. Slot [07:00,07:10). Sched 07:00 is in slot. -> T'],
            ['07:00', '07:10:00', false, 'General: 07:00 sched, 07:10 current. Slot [07:10,07:20). Sched 07:00 not in slot. -> F'],
            ['07:30:00', '07:21:10', false, 'General: 07:30:00 sched, 07:21:10 current. Slot [07:20,07:30). Sched 07:30 not < 07:30. -> F']
        ];
    }

    // --- New Test Methods using Data Providers ---

    /**
     * @dataProvider everydayTriggerDataProvider
     */
    public function testShouldRunNowForEverydayTrigger(string $currentTimeToMock, bool $expectedResult, string $message): void
    {
        $timeZone = new \DateTimeZone(Consts::TIMEZONE);
        Carbon::setTestNow(Carbon::parse($currentTimeToMock, $timeZone));
        $trigger = new TimerTrigger("everyday", "10:00", "Test everyday 10:00"); // Scheduled time is 10:00
        // Note: The interval for these original tests was 10 minutes.
        $this->assertSame($expectedResult, $trigger->shouldRunNow(10), $message);
    }

    /**
     * @dataProvider todayTriggerDataProvider
     */
    public function testShouldRunNowForTodayTrigger(string $currentTimeToMock, bool $expectedResult, string $message): void
    {
        $timeZone = new \DateTimeZone(Consts::TIMEZONE);
        // Set a fixed "today" for the context of this test via constructor's Carbon::now()
        Carbon::setTestNow(Carbon::parse("2023-05-10 12:00:00", $timeZone));
        $trigger = new TimerTrigger("today", "14:30", "Test today 14:30"); // Scheduled time is 14:30 on 2023-05-10

        // Now, set the "current time" for specific assertions
        Carbon::setTestNow(Carbon::parse($currentTimeToMock, $timeZone));
        // Note: The interval for these original tests was 5 minutes.
        $this->assertSame($expectedResult, $trigger->shouldRunNow(5), $message);
    }

    /**
     * @dataProvider specificDateTriggerDataProvider
     */
    public function testShouldRunNowForSpecificDateTrigger(string $currentTimeToMock, bool $expectedResult, string $message): void
    {
        $timeZone = new \DateTimeZone(Consts::TIMEZONE);
        Carbon::setTestNow(Carbon::parse($currentTimeToMock, $timeZone));
        // For specific date triggers, the constructor's Carbon::now() doesn't affect $this->actualDate if date is absolute.
        $trigger = new TimerTrigger("2023-06-15", "08:00", "Test Specific Date 08:00"); // Scheduled for 2023-06-15 08:00

        // Note: The interval for these original tests was 15 minutes.
        $this->assertSame($expectedResult, $trigger->shouldRunNow(15), $message);
    }

    /**
     * @dataProvider generalShouldRunNowDataProvider
     */
    public function testGeneralShouldRunNowScenarios(string $scheduledTimeForTrigger, string $currentTimeForCheck, bool $expectedResult, string $assertionMessage): void
    {
        $timeZone = new \DateTimeZone(Consts::TIMEZONE);

        // Set base date for constructor when TimerTrigger uses 'today'
        // This ensures $trigger->actualDate is self::TEST_DATE
        Carbon::setTestNow(Carbon::parse(self::TEST_DATE . ' 00:00:00', $timeZone));
        $trigger = new TimerTrigger('today', $scheduledTimeForTrigger, 'Test general ' . $scheduledTimeForTrigger);

        // Set current time for the shouldRunNow check
        $now = Carbon::parse(self::TEST_DATE . ' ' . $currentTimeForCheck, $timeZone);
        Carbon::setTestNow($now);

        $this->assertSame($expectedResult, $trigger->shouldRunNow(self::TRIGGER_DURATION_MINS), $assertionMessage);
    }

    // --- Kept Test Methods ---

    public function testShouldRunNowHandlesTomorrowCorrectly(): void
    {
        $timeZone = new \DateTimeZone(Consts::TIMEZONE);

        // Set current "day" to 2023-07-01. So "tomorrow" for the trigger logic will be 2023-07-02.
        Carbon::setTestNow(Carbon::parse("2023-07-01 10:00:00", $timeZone));
        $trigger = new TimerTrigger("tomorrow", "11:00", "Test");
        
        // Test time on the actual "tomorrow" (2023-07-02)
        $testTimeOnActualTomorrow = Carbon::parse("2023-07-02 11:00:00", $timeZone);
        Carbon::withTestNow($testTimeOnActualTomorrow, function() use ($trigger) { // Use withTestNow to isolate this specific "now"
            // Interval for this original test was 5
            $this->assertTrue($trigger->shouldRunNow(5), "Should run on the actual 'tomorrow' at the set time");
        });

        // Test time on "today" (2023-07-01) (should not run)
        Carbon::setTestNow(Carbon::parse("2023-07-01 11:00:00", $timeZone));
        $this->assertFalse($trigger->shouldRunNow(5), "Should not run on 'today' when date is 'tomorrow'");
    }

    public function testShouldRunNowReturnsFalseForInvalidTimeFormat(): void
    {
        $trigger = new TimerTrigger("everyday", "invalid-time", "Test");
        Carbon::setTestNow(Carbon::parse("2023-01-01 10:00:00", new \DateTimeZone(Consts::TIMEZONE)));
        $this->assertFalse($trigger->shouldRunNow(10));
    }

    public function testShouldRunNowHandlesNowPlusXMinsCorrectlyAfterConstructorProcessing(): void
    {
        $timezone = new \DateTimeZone(Consts::TIMEZONE);

        Carbon::setTestNow(Carbon::create(2023, 1, 1, 10, 0, 0, $timezone));
        $trigger = new TimerTrigger("today", "now +30 mins", "Test 'now +30 mins'");
        $this->assertEquals('10:30', $trigger->getTime(), "Time should be calculated to 10:30");
        $this->assertEquals(Carbon::create(2023, 1, 1, 0, 0, 0, $timezone)->format('Y/m/d'), $trigger->getActualDate(), "ActualDate should be 2023/01/01");

        $duration = 5;

        Carbon::setTestNow(Carbon::create(2023, 1, 1, 10, 30, 0, $timezone));
        $this->assertTrue($trigger->shouldRunNow($duration), "Should run at the calculated time (10:30)");

        Carbon::setTestNow(Carbon::create(2023, 1, 1, 10, 32, 0, $timezone));
        $this->assertTrue($trigger->shouldRunNow($duration), "Should run within interval of calculated time (10:32)");

        Carbon::setTestNow(Carbon::create(2023, 1, 1, 10, 34, 59, $timezone));
        $this->assertTrue($trigger->shouldRunNow($duration), "Should run at end of interval of calculated time (10:34:59)");

        Carbon::setTestNow(Carbon::create(2023, 1, 1, 10, 35, 0, $timezone));
        $this->assertFalse($trigger->shouldRunNow($duration), "Should not run outside interval of calculated time (10:35)");

        Carbon::setTestNow(Carbon::create(2023, 1, 1, 10, 0, 0, $timezone));
        $this->assertFalse($trigger->shouldRunNow($duration), "Should not run at original 'now' time (10:00), as target is 10:30");

        Carbon::setTestNow(Carbon::create(2023, 1, 1, 11, 0, 0, $timezone));
        $this->assertFalse($trigger->shouldRunNow($duration), "Should not run if 'now' is significantly past the window (11:00)");

        Carbon::setTestNow(Carbon::create(2024, 5, 10, 15, 0, 0, $timezone));
        $everydayTrigger = new TimerTrigger("everyday", "now +10 mins", "Test everyday now +10");
        $this->assertEquals("15:10", $everydayTrigger->getTime());

        Carbon::setTestNow(Carbon::create(2024, 5, 10, 15, 10, 0, $timezone));
        $this->assertTrue($everydayTrigger->shouldRunNow($duration));

        Carbon::setTestNow(Carbon::create(2024, 5, 11, 15, 10, 0, $timezone));
        $this->assertTrue($everydayTrigger->shouldRunNow($duration), "Everyday trigger should run on subsequent days at the calculated time");

        Carbon::setTestNow(Carbon::create(2024, 5, 10, 15, 0, 0, $timezone));
        $this->assertFalse($everydayTrigger->shouldRunNow($duration));

        Carbon::setTestNow(Carbon::create(2024, 5, 10, 15, 15, 0, $timezone));
        $this->assertFalse($everydayTrigger->shouldRunNow($duration));

        Carbon::setTestNow();
    }
}
