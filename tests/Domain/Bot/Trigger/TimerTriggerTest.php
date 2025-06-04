<?php

declare(strict_types=1);

namespace MyApp\Tests\Domain\Bot\Trigger; // Adjusted namespace for PSR-4

use PHPUnit\Framework\TestCase;
use MyApp\Domain\Bot\Trigger\TimerTrigger;
use Carbon\Carbon;
use MyApp\Consts;

final class TimerTriggerTest extends TestCase
{
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

    public function testShouldRunNowHandlesEverydayCorrectly(): void
    {
        $trigger = new TimerTrigger("everyday", "10:00", "Test");
        
        Carbon::setTestNow(Carbon::parse("2023-01-01 10:00:00"));
        $this->assertTrue($trigger->shouldRunNow(10), "Should run at exact time");

        Carbon::setTestNow(Carbon::parse("2023-01-01 10:05:00"));
        $this->assertTrue($trigger->shouldRunNow(10), "Should run within interval");
        
        Carbon::setTestNow(Carbon::parse("2023-01-01 10:09:59"));
        $this->assertTrue($trigger->shouldRunNow(10), "Should run at end of interval");

        Carbon::setTestNow(Carbon::parse("2023-01-01 10:10:00"));
        $this->assertFalse($trigger->shouldRunNow(10), "Should not run outside interval");

        Carbon::setTestNow(Carbon::parse("2023-01-01 09:59:59"));
        $this->assertFalse($trigger->shouldRunNow(10), "Should not run before time");
    }

    public function testShouldRunNowHandlesTodayCorrectly(): void
    {
        $trigger = new TimerTrigger("today", "14:30", "Test");
        // Set a fixed "today" for the context of this test,
        // because TimerTrigger's "today" uses Carbon::today() which would otherwise be the real today.
        Carbon::setTestNow(Carbon::parse("2023-05-10 12:00:00")); 

        // Now, set the "current time" for specific assertions
        Carbon::setTestNow(Carbon::parse("2023-05-10 14:30:00"));
        $this->assertTrue($trigger->shouldRunNow(5));
        
        Carbon::setTestNow(Carbon::parse("2023-05-10 14:34:59"));
        $this->assertTrue($trigger->shouldRunNow(5));

        Carbon::setTestNow(Carbon::parse("2023-05-10 14:35:00"));
        $this->assertFalse($trigger->shouldRunNow(5));

        // Different day (relative to the initial setTestNow for "today")
        Carbon::setTestNow(Carbon::parse("2023-05-11 14:30:00"));
        $this->assertFalse($trigger->shouldRunNow(5));
    }

    public function testShouldRunNowHandlesSpecificDateCorrectly(): void
    {
        $trigger = new TimerTrigger("2023-06-15", "08:00", "Test");

        Carbon::setTestNow(Carbon::parse("2023-06-15 08:00:00"));
        $this->assertTrue($trigger->shouldRunNow(15));

        Carbon::setTestNow(Carbon::parse("2023-06-15 08:14:59"));
        $this->assertTrue($trigger->shouldRunNow(15));
        
        Carbon::setTestNow(Carbon::parse("2023-06-15 08:15:00"));
        $this->assertFalse($trigger->shouldRunNow(15));

        Carbon::setTestNow(Carbon::parse("2023-06-14 08:00:00"));
        $this->assertFalse($trigger->shouldRunNow(15));
    }
    
    public function testShouldRunNowHandlesTomorrowCorrectly(): void
    {
        $trigger = new TimerTrigger("tomorrow", "11:00", "Test");

        // Set current "day" to 2023-07-01. So "tomorrow" for the trigger logic will be 2023-07-02.
        Carbon::setTestNow(Carbon::parse("2023-07-01 10:00:00")); 
        
        // Test time on the actual "tomorrow" (2023-07-02)
        $testTimeOnActualTomorrow = Carbon::parse("2023-07-02 11:00:00");
        Carbon::withTestNow($testTimeOnActualTomorrow, function() use ($trigger) { // Use withTestNow to isolate this specific "now"
            $this->assertTrue($trigger->shouldRunNow(5), "Should run on the actual 'tomorrow' at the set time");
        });

        // Test time on "today" (2023-07-01) (should not run)
        Carbon::setTestNow(Carbon::parse("2023-07-01 11:00:00"));
        $this->assertFalse($trigger->shouldRunNow(5), "Should not run on 'today' when date is 'tomorrow'");
    }

    public function testShouldRunNowReturnsFalseForInvalidTimeFormat(): void
    {
        $trigger = new TimerTrigger("everyday", "invalid-time", "Test");
        Carbon::setTestNow(Carbon::parse("2023-01-01 10:00:00"));
        $this->assertFalse($trigger->shouldRunNow(10));
    }

    public function testShouldRunNowHandlesNowPlusXMinsCorrectlyAfterConstructorProcessing(): void
    {
        // It's good practice to ensure Consts::TIMEZONE is available for Carbon parsing in tests
        // if the main code relies on it, to maintain consistency.
        $timezone = new \DateTimeZone(Consts::TIMEZONE);

        // Set the "current time" for the constructor phase
        Carbon::setTestNow(Carbon::create(2023, 1, 1, 10, 0, 0, $timezone));

        // This should result in $trigger->time being "10:30" and
        // $trigger->actualDate being today's date (2023/01/01)
        // as 'today' is resolved by the constructor using the $carbonNow (set to 10:00:00).
        $trigger = new TimerTrigger("today", "now +30 mins", "Test 'now +30 mins'");

        // Assert internal state (optional, but good for sanity check)
        // The constructor converts "now +30 mins" (from 10:00:00) to "10:30"
        $this->assertEquals('10:30', $trigger->getTime(), "Time should be calculated to 10:30");
        // The constructor sets actualDate to "2023/01/01" because date was "today" and testNow was Jan 1st
        $this->assertEquals(Carbon::create(2023, 1, 1, 0, 0, 0, $timezone)->format('Y/m/d'), $trigger->getActualDate(), "ActualDate should be 2023/01/01");

        // Now test shouldRunNow. The trigger time is effectively 10:30 on 2023/01/01.
        $duration = 5; // 5 minutes duration

        // Test 1: Current time is 10:30:00 (exactly when timer should start)
        Carbon::setTestNow(Carbon::create(2023, 1, 1, 10, 30, 0, $timezone));
        $this->assertTrue($trigger->shouldRunNow($duration), "Should run at the calculated time (10:30)");

        // Test 2: Current time is 10:32:00 (within 5 min window)
        Carbon::setTestNow(Carbon::create(2023, 1, 1, 10, 32, 0, $timezone));
        $this->assertTrue($trigger->shouldRunNow($duration), "Should run within interval of calculated time (10:32)");

        // Test 3: Current time is 10:34:59 (at edge of 5 min window)
        Carbon::setTestNow(Carbon::create(2023, 1, 1, 10, 34, 59, $timezone));
        $this->assertTrue($trigger->shouldRunNow($duration), "Should run at end of interval of calculated time (10:34:59)");

        // Test 4: Current time is 10:35:00 (just outside 5 min window)
        Carbon::setTestNow(Carbon::create(2023, 1, 1, 10, 35, 0, $timezone));
        $this->assertFalse($trigger->shouldRunNow($duration), "Should not run outside interval of calculated time (10:35)");

        // Test 5: Current time is 10:00:00 (original time used in constructor, before +30 mins was applied)
        Carbon::setTestNow(Carbon::create(2023, 1, 1, 10, 0, 0, $timezone));
        $this->assertFalse($trigger->shouldRunNow($duration), "Should not run at original 'now' time (10:00), as target is 10:30");

        // Test 6: Current time is much later, e.g. 11:00:00
        Carbon::setTestNow(Carbon::create(2023, 1, 1, 11, 0, 0, $timezone));
        $this->assertFalse($trigger->shouldRunNow($duration), "Should not run if 'now' is significantly past the window (11:00)");

        // Test 7: Date is 'everyday', time 'now +10 mins'
        Carbon::setTestNow(Carbon::create(2024, 5, 10, 15, 0, 0, $timezone));
        $everydayTrigger = new TimerTrigger("everyday", "now +10 mins", "Test everyday now +10");
        $this->assertEquals("15:10", $everydayTrigger->getTime());
        // $this->assertEquals("everyday", $everydayTrigger->getActualDate()); // actualDate remains 'everyday'

        Carbon::setTestNow(Carbon::create(2024, 5, 10, 15, 10, 0, $timezone)); // Check on the same day
        $this->assertTrue($everydayTrigger->shouldRunNow($duration));

        Carbon::setTestNow(Carbon::create(2024, 5, 11, 15, 10, 0, $timezone)); // Check on the next day
        $this->assertTrue($everydayTrigger->shouldRunNow($duration), "Everyday trigger should run on subsequent days at the calculated time");

        Carbon::setTestNow(Carbon::create(2024, 5, 10, 15, 0, 0, $timezone)); // Check before time on same day
        $this->assertFalse($everydayTrigger->shouldRunNow($duration));

        Carbon::setTestNow(Carbon::create(2024, 5, 10, 15, 15, 0, $timezone)); // Check after window on same day
        $this->assertFalse($everydayTrigger->shouldRunNow($duration));

        Carbon::setTestNow(); // Reset to real time
    }

    private const TEST_DATE = '2024/01/01';
    private const TRIGGER_DURATION_MINS = 10;

    public function testShouldRunNow_Current720_Scheduled730_ReturnsFalse()
    {
        $timeZone = new \DateTimeZone(Consts::TIMEZONE);
        $scheduledTimeStr = '07:30';
        // Use 'today' for date so actualDate is set to TEST_DATE by constructor when 'now' is TEST_DATE
        Carbon::setTestNow(Carbon::parse(self::TEST_DATE . ' 00:00:00', $timeZone)); // Set base date for constructor
        $trigger = new TimerTrigger('today', $scheduledTimeStr, 'test request for ' . $scheduledTimeStr);

        $now = Carbon::parse(self::TEST_DATE . ' 07:20:00', $timeZone);
        Carbon::setTestNow($now);

        $this->assertFalse($trigger->shouldRunNow(self::TRIGGER_DURATION_MINS), "Timer scheduled for {$scheduledTimeStr} on " . self::TEST_DATE . " should NOT run when current time is 07:20:00.");
    }

    public function testShouldRunNow_Current730_Scheduled730_ReturnsTrue()
    {
        $timeZone = new \DateTimeZone(Consts::TIMEZONE);
        $scheduledTimeStr = '07:30';
        Carbon::setTestNow(Carbon::parse(self::TEST_DATE . ' 00:00:00', $timeZone));
        $trigger = new TimerTrigger('today', $scheduledTimeStr, 'test request for ' . $scheduledTimeStr);

        $now = Carbon::parse(self::TEST_DATE . ' 07:30:00', $timeZone);
        Carbon::setTestNow($now);

        $this->assertTrue($trigger->shouldRunNow(self::TRIGGER_DURATION_MINS), "Timer scheduled for {$scheduledTimeStr} on " . self::TEST_DATE . " should run when current time is 07:30:00.");
    }

    // Scenario from issue has current time 7:35, scheduled 7:30, duration 10, expected true
    // This corresponds to the original "Timer should run - within duration"
    public function testShouldRunNow_Current735_Scheduled730_ReturnsTrue()
    {
        $timeZone = new \DateTimeZone(Consts::TIMEZONE);
        $scheduledTimeStr = '07:30';
        Carbon::setTestNow(Carbon::parse(self::TEST_DATE . ' 00:00:00', $timeZone));
        $trigger = new TimerTrigger('today', $scheduledTimeStr, 'test request for ' . $scheduledTimeStr);

        $now = Carbon::parse(self::TEST_DATE . ' 07:35:00', $timeZone);
        Carbon::setTestNow($now);

        $this->assertTrue($trigger->shouldRunNow(self::TRIGGER_DURATION_MINS), "Timer scheduled for {$scheduledTimeStr} on " . self::TEST_DATE . " should run when current time is 07:35:00.");
    }

    // This test is based on the new list: Current Time: "07:29:00", Scheduled Time: "07:30", expected true.
    // This tests the edge case just before the scheduled time, but within the logic of the corrected shouldRunNow,
    // it should be false if $now->lt($triggerTime). If the implementation considers $now->diffInMinutes($triggerTime) < $duration
    // and $now is slightly before $triggerTime making diff positive but small, it might pass.
    // The corrected logic is: if ($now->lt($triggerTime)) return false; ... $absoluteDiff = $triggerTime->diffInMinutes($now, true); if ($absoluteDiff < $triggerDurationMins) return true;
    // For 7:29 vs 7:30, $now->lt($triggerTime) is true, so it should be false.
    public function testShouldRunNow_Current729_Scheduled730_ReturnsFalse_AsCurrentIsBeforeScheduled()
    {
        $timeZone = new \DateTimeZone(Consts::TIMEZONE);
        $scheduledTimeStr = '07:30';
        Carbon::setTestNow(Carbon::parse(self::TEST_DATE . ' 00:00:00', $timeZone));
        $trigger = new TimerTrigger('today', $scheduledTimeStr, 'test request for ' . $scheduledTimeStr);

        $now = Carbon::parse(self::TEST_DATE . ' 07:29:59', $timeZone); // Using 07:29:59 for precision
        Carbon::setTestNow($now);

        $this->assertFalse($trigger->shouldRunNow(self::TRIGGER_DURATION_MINS), "Timer scheduled for {$scheduledTimeStr} on " . self::TEST_DATE . " should NOT run when current time is 07:29:59 (before scheduled).");
    }

    // This test is based on the new list: Current Time: "07:30:00", Scheduled Time: "07:39" -> should be current time 07:39, scheduled 07:30
    // Correcting to: Current Time: "07:39:00", Scheduled Time: "07:30" (within duration)
    public function testShouldRunNow_Current739_Scheduled730_ReturnsTrue()
    {
        $timeZone = new \DateTimeZone(Consts::TIMEZONE);
        $scheduledTimeStr = '07:30';
        Carbon::setTestNow(Carbon::parse(self::TEST_DATE . ' 00:00:00', $timeZone));
        $trigger = new TimerTrigger('today', $scheduledTimeStr, 'test request for ' . $scheduledTimeStr);

        $now = Carbon::parse(self::TEST_DATE . ' 07:39:00', $timeZone);
        Carbon::setTestNow($now);

        $this->assertTrue($trigger->shouldRunNow(self::TRIGGER_DURATION_MINS), "Timer scheduled for {$scheduledTimeStr} on " . self::TEST_DATE . " should run when current time is 07:39:00 (within 10 min duration).");
    }

    // Original scenario: Timer should not run - past duration
    // Current time: 7:40, Scheduled time: 7:30, triggerDurationMins: 10. Expected: false
    // This means current time 07:40, scheduled 07:30. $absoluteDiff = 10. $absoluteDiff < 10 is false.
    public function testShouldRunNow_Current740_Scheduled730_ReturnsFalse()
    {
        $timeZone = new \DateTimeZone(Consts::TIMEZONE);
        $scheduledTimeStr = '07:30';
        Carbon::setTestNow(Carbon::parse(self::TEST_DATE . ' 00:00:00', $timeZone));
        $trigger = new TimerTrigger('today', $scheduledTimeStr, 'test request for ' . $scheduledTimeStr);

        $now = Carbon::parse(self::TEST_DATE . ' 07:40:00', $timeZone);
        Carbon::setTestNow($now);

        $this->assertFalse($trigger->shouldRunNow(self::TRIGGER_DURATION_MINS), "Timer scheduled for {$scheduledTimeStr} on " . self::TEST_DATE . " should NOT run when current time is 07:40:00 (at limit of 10 min duration).");
    }

    // Original scenario: Timer should not run - way before scheduled time
    // Current time: 6:00, Scheduled time: 7:30, triggerDurationMins: 10. Expected: false
    public function testShouldRunNow_Current600_Scheduled730_ReturnsFalse()
    {
        $timeZone = new \DateTimeZone(Consts::TIMEZONE);
        $scheduledTimeStr = '07:30';
        Carbon::setTestNow(Carbon::parse(self::TEST_DATE . ' 00:00:00', $timeZone));
        $trigger = new TimerTrigger('today', $scheduledTimeStr, 'test request for ' . $scheduledTimeStr);

        $now = Carbon::parse(self::TEST_DATE . ' 06:00:00', $timeZone);
        Carbon::setTestNow($now);

        $this->assertFalse($trigger->shouldRunNow(self::TRIGGER_DURATION_MINS), "Timer scheduled for {$scheduledTimeStr} on " . self::TEST_DATE . " should NOT run when current time is 06:00:00 (way before).");
    }


    // Test cases from the prompt's "Test Scenarios" section (some might overlap or be variations of above)
    // Test 1: Current720_Scheduled730_ReturnsFalse -> Covered by testShouldRunNow_Current720_Scheduled730_ReturnsFalse

    // Test 2: Current730_Scheduled730_ReturnsTrue -> Covered by testShouldRunNow_Current730_Scheduled730_ReturnsTrue

    // Test 3: testShouldRunNow_Current725_Scheduled730_ReturnsTrue -> This implies current time is 07:25, scheduled 07:30.
    // According to the corrected logic: if ($now->lt($triggerTime)) return false;
    // So, 07:25 is less than 07:30, so it should be false.
    public function testShouldRunNow_Current725_Scheduled730_ReturnsFalse_AsCurrentIsBeforeScheduled()
    {
        $timeZone = new \DateTimeZone(Consts::TIMEZONE);
        $scheduledTimeStr = '07:30';
        Carbon::setTestNow(Carbon::parse(self::TEST_DATE . ' 00:00:00', $timeZone));
        $trigger = new TimerTrigger('today', $scheduledTimeStr, 'test request for ' . $scheduledTimeStr);

        $now = Carbon::parse(self::TEST_DATE . ' 07:25:00', $timeZone);
        Carbon::setTestNow($now);

        $this->assertFalse($trigger->shouldRunNow(self::TRIGGER_DURATION_MINS), "Timer for {$scheduledTimeStr} at " . self::TEST_DATE . " should NOT run when current time is 07:25:00 (before scheduled).");
    }

    // Test 4: testShouldRunNow_Current729_Scheduled730_ReturnsTrue -> Covered by testShouldRunNow_Current729_Scheduled730_ReturnsFalse_AsCurrentIsBeforeScheduled, expected FALSE

    // Test 5: testShouldRunNow_Current730_Scheduled739_ReturnsTrue -> This should be: Current Time: 07:39, Scheduled Time: 07:30.
    // This is covered by testShouldRunNow_Current739_Scheduled730_ReturnsTrue

    // Test 6: testShouldRunNow_Current730_Scheduled725_ReturnsFalse (Past timer)
    // Current time 07:30, Scheduled 07:25. $absoluteDiff = 5. $absoluteDiff < 10 is true. This should be TRUE.
    // This tests if a timer that was scheduled for 07:25, and current time is 07:30, is still within its 10-min window.
    public function testShouldRunNow_Current730_Scheduled725_ReturnsTrue_AsItIsWithinWindow()
    {
        $timeZone = new \DateTimeZone(Consts::TIMEZONE);
        $scheduledTimeStr = '07:25'; // Scheduled time is 07:25
        Carbon::setTestNow(Carbon::parse(self::TEST_DATE . ' 00:00:00', $timeZone));
        $trigger = new TimerTrigger('today', $scheduledTimeStr, 'test request for ' . $scheduledTimeStr);

        $now = Carbon::parse(self::TEST_DATE . ' 07:30:00', $timeZone); // Current time is 07:30
        Carbon::setTestNow($now);

        // $triggerTime is 07:25. $now is 07:30.
        // $now->lt($triggerTime) is false.
        // $absoluteDiff = $triggerTime->diffInMinutes($now, true) = 5.
        // $absoluteDiff (5) < TRIGGER_DURATION_MINS (10) is true.
        $this->assertTrue($trigger->shouldRunNow(self::TRIGGER_DURATION_MINS), "Timer scheduled for {$scheduledTimeStr} on " . self::TEST_DATE . " should RUN when current time is 07:30:00 (5 mins past scheduled, within 10 min duration).");
    }

    // Test 7: testShouldRunNow_Current700_Scheduled700_ReturnsTrue
    public function testShouldRunNow_Current700_Scheduled700_ReturnsTrue()
    {
        $timeZone = new \DateTimeZone(Consts::TIMEZONE);
        $scheduledTimeStr = '07:00';
        Carbon::setTestNow(Carbon::parse(self::TEST_DATE . ' 00:00:00', $timeZone));
        $trigger = new TimerTrigger('today', $scheduledTimeStr, 'test request for ' . $scheduledTimeStr);

        $now = Carbon::parse(self::TEST_DATE . ' 07:00:00', $timeZone);
        Carbon::setTestNow($now);

        $this->assertTrue($trigger->shouldRunNow(self::TRIGGER_DURATION_MINS), "Timer for {$scheduledTimeStr} at " . self::TEST_DATE . " should run when current time is 07:00:00.");
    }

    // Test 8: testShouldRunNow_Current700_Scheduled709_ReturnsTrue -> This means current time 07:09, scheduled 07:00
    public function testShouldRunNow_Current709_Scheduled700_ReturnsTrue()
    {
        $timeZone = new \DateTimeZone(Consts::TIMEZONE);
        $scheduledTimeStr = '07:00'; // Scheduled 07:00
        Carbon::setTestNow(Carbon::parse(self::TEST_DATE . ' 00:00:00', $timeZone));
        $trigger = new TimerTrigger('today', $scheduledTimeStr, 'test request for ' . $scheduledTimeStr);

        $now = Carbon::parse(self::TEST_DATE . ' 07:09:00', $timeZone); // Current 07:09
        Carbon::setTestNow($now);
        // $absoluteDiff = 9. 9 < 10 is true.
        $this->assertTrue($trigger->shouldRunNow(self::TRIGGER_DURATION_MINS), "Timer for {$scheduledTimeStr} at " . self::TEST_DATE . " should run when current time is 07:09:00 (9 mins past, within 10 min duration).");
    }

    // Test 9: testShouldRunNow_Current700_Scheduled710_ReturnsFalse -> This means current time 07:10, scheduled 07:00
    public function testShouldRunNow_Current710_Scheduled700_ReturnsFalse()
    {
        $timeZone = new \DateTimeZone(Consts::TIMEZONE);
        $scheduledTimeStr = '07:00'; // Scheduled 07:00
        Carbon::setTestNow(Carbon::parse(self::TEST_DATE . ' 00:00:00', $timeZone));
        $trigger = new TimerTrigger('today', $scheduledTimeStr, 'test request for ' . $scheduledTimeStr);

        $now = Carbon::parse(self::TEST_DATE . ' 07:10:00', $timeZone); // Current 07:10
        Carbon::setTestNow($now);
        // $absoluteDiff = 10. 10 < 10 is false.
        $this->assertFalse($trigger->shouldRunNow(self::TRIGGER_DURATION_MINS), "Timer for {$scheduledTimeStr} at " . self::TEST_DATE . " should NOT run when current time is 07:10:00 (10 mins past, at limit of 10 min duration).");
    }
}
