<?php

declare(strict_types=1);

namespace MyApp\Tests\Domain\Bot\Trigger; // Adjusted namespace for PSR-4

use PHPUnit\Framework\TestCase;
use MyApp\Domain\Bot\Trigger\TimerTrigger;
use Carbon\Carbon;

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

    public function testShouldRunNowReturnsFalseForNowPlusXMinsTimeFormat(): void
    {
        $trigger = new TimerTrigger("today", "now +30 mins", "Test");
        Carbon::setTestNow(Carbon::parse("2023-01-01 10:00:00"));
        $this->assertFalse($trigger->shouldRunNow(10));
    }
}
