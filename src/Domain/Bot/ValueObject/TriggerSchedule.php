<?php declare(strict_types=1);

namespace MyApp\Domain\Bot\ValueObject;

use Carbon\Carbon;
use MyApp\Consts;

/**
 * Handles the scheduling logic for a trigger, including relative date/time resolution.
 */
class TriggerSchedule
{
    private string $originalDate;
    private string $originalTime;
    private string $resolvedDate;
    private string $resolvedTime;

    public function __construct(string $date, string $time)
    {
        $carbonNow = new Carbon(timezone: new \DateTimeZone(Consts::TIMEZONE));

        $this->originalDate = $date;
        $this->originalTime = $time;

        // Resolve relative time (e.g., "now +10 mins")
        if (preg_match('/^now \+(\d+) mins$/', $time, $matches)) {
            $this->resolvedTime = $carbonNow->copy()->addMinutes((int)$matches[1])->format('H:i');
        } else {
            $this->resolvedTime = $time;
        }

        // Resolve relative date (e.g., "today", "tomorrow")
        switch ($date) {
            case 'everyday':
                $this->resolvedDate = 'everyday';
                break;
            case 'today':
                $this->resolvedDate = $carbonNow->copy()->format('Y/m/d');
                break;
            case 'tomorrow':
                $this->resolvedDate = $carbonNow->copy()->addDay()->format('Y/m/d');
                break;
            case 'day after tomorrow':
                $this->resolvedDate = $carbonNow->copy()->addDays(2)->format('Y/m/d');
                break;
            default:
                // Assumes a specific date string or already resolved date
                try {
                    // Try parsing and normalization
                    $this->resolvedDate = Carbon::parse($date, new \DateTimeZone(Consts::TIMEZONE))->format('Y/m/d');
                } catch (\Exception $e) {
                    // Fallback to original if parsing fails
                    $this->resolvedDate = $date;
                }
                break;
        }
    }

    public function getResolvedDate(): string
    {
        return $this->resolvedDate;
    }

    public function getResolvedTime(): string
    {
        return $this->resolvedTime;
    }

    public function getOriginalDate(): string
    {
        return $this->originalDate;
    }

    public function getOriginalTime(): string
    {
        return $this->originalTime;
    }

    public function shouldRunNow(int $timerTriggeredByNMins): bool
    {
        $carbonNow = new Carbon(timezone: new \DateTimeZone(Consts::TIMEZONE));

        try {
            list($hour, $minute) = sscanf($this->resolvedTime, "%d:%d");
            if (is_null($hour) || is_null($minute)) {
                return false;
            }
        } catch (\Exception $e) {
            return false;
        }

        try {
            if ($this->resolvedDate === 'everyday') {
                $triggerDateCarbon = $carbonNow->copy()->startOfDay();
            } else {
                // Try to parse resolvedDate
                $triggerDateCarbon = Carbon::parse($this->resolvedDate, new \DateTimeZone(Consts::TIMEZONE))->startOfDay();
            }
        } catch (\Exception $e) {
            return false;
        }

        if (!$triggerDateCarbon) {
            return false;
        }

        $triggerDateTimeCarbon = $triggerDateCarbon->hour($hour)->minute($minute)->second(0);

        // Calculate current time slot
        $slotMinuteValue = floor($carbonNow->minute / $timerTriggeredByNMins) * $timerTriggeredByNMins;
        $slotStartTime = $carbonNow->copy()->minute((int)$slotMinuteValue)->second(0)->microsecond(0);
        $slotEndTime = $slotStartTime->copy()->addMinutes($timerTriggeredByNMins);

        // Timer should run if its scheduled time is within the current slot
        return $triggerDateTimeCarbon->gte($slotStartTime) && $triggerDateTimeCarbon->lt($slotEndTime);
    }

    public function __toString(): string
    {
        return "{$this->resolvedDate} {$this->resolvedTime}";
    }
}
