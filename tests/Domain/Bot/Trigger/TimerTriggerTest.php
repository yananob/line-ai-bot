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
}
