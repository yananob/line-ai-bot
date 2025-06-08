<?php declare(strict_types=1);

namespace MyApp\Domain\Bot\Trigger;

use Carbon\Carbon;
use MyApp\Consts;

class TimerTrigger implements Trigger
{
    private ?string $id = null;
    private string $date;
    private string $time;
    private string $request;
    private string $actualDate;

    public function __construct(string $date, string $time, string $request)
    {
        $carbonNow = new Carbon(timezone: new \DateTimeZone(Consts::TIMEZONE));

        $this->date = $date;
        $this->request = $request;

        // Handle time
        if (preg_match('/^now +(\d+) mins$/', $time, $matches)) {
            $this->time = $carbonNow->copy()->addMinutes((int)$matches[1])->format('H:i');
        } else {
            $this->time = $time;
        }

        // Handle date
        switch ($this->date) {
            case 'everyday':
                $this->actualDate = 'everyday'; // Special case, doesn't resolve to a specific date
                break;
            case 'today':
                $this->actualDate = $carbonNow->copy()->format('Y/m/d');
                $this->date = $this->actualDate;
                break;
            case 'tomorrow':
                $this->actualDate = $carbonNow->copy()->addDay()->format('Y/m/d');
                $this->date = $this->actualDate;
                break;
            case 'day after tomorrow':
                $this->actualDate = $carbonNow->copy()->addDays(2)->format('Y/m/d');
                $this->date = $this->actualDate;
                break;
            default:
                // Assumes a specific date string like YYYY/MM/DD or YYYY-MM-DD
                $this->actualDate = Carbon::parse($this->date, new \DateTimeZone(Consts::TIMEZONE))->format('Y/m/d');
                // No need to update $this->date here as it's already specific
                break;
        }
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function getEvent(): string
    {
        return "timer";
    }

    public function getRequest(): string
    {
        return $this->request;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'event' => $this->getEvent(),
            'date' => $this->date,
            'time' => $this->time,
            'request' => $this->request,
        ];
    }

    public function getDate(): string
    {
        return $this->date;
    }

    public function getTime(): string
    {
        return $this->time;
    }

    public function getActualDate(): string
    {
        return $this->actualDate;
    }

    public function shouldRunNow(int $timerTriggeredByNMins): bool
    {
        error_log("--- testShouldRunNowHandlesEverydayCorrectly DEBUG START ---");
        error_log("Param \$timerTriggeredByNMins: " . $timerTriggeredByNMins);

        $carbonNow = new Carbon(timezone: new \DateTimeZone(Consts::TIMEZONE));
        error_log("Current Time (\$carbonNow): " . $carbonNow->toIso8601String());
        error_log("Current Time Minute (\$carbonNow->minute): " . $carbonNow->minute);

        try {
            list($hour, $minute) = sscanf($this->time, "%d:%d");
            if (is_null($hour) || is_null($minute)) {
                error_log("Error: Invalid time format for \$this->time: " . $this->time);
                error_log("--- testShouldRunNowHandlesEverydayCorrectly DEBUG END (early return) ---");
                return false;
            }
            error_log("Parsed \$hour: " . $hour . ", \$minute: " . $minute . " from \$this->time: " . $this->time);
        } catch (\Exception $e) {
            error_log("Exception during time parsing: " . $e->getMessage());
            error_log("--- testShouldRunNowHandlesEverydayCorrectly DEBUG END (early return) ---");
            return false;
        }

        $triggerDateCarbon = null;
        error_log("\$this->actualDate: " . $this->actualDate);

        try {
            if ($this->actualDate === 'everyday') {
                error_log("Condition: \$this->actualDate === 'everyday'");
                $triggerDateCarbon = $carbonNow->copy()->startOfDay();
                error_log("For 'everyday', \$triggerDateCarbon (from \$carbonNow->copy()->startOfDay()): " . $triggerDateCarbon->toIso8601String());
            } else {
                error_log("Condition: \$this->actualDate !== 'everyday', value: " . $this->actualDate);
                $triggerDateCarbon = Carbon::parse($this->actualDate, new \DateTimeZone(Consts::TIMEZONE))->startOfDay();
                error_log("For specific date, \$triggerDateCarbon (from Carbon::parse(\$this->actualDate)->startOfDay()): " . $triggerDateCarbon->toIso8601String());
            }
        } catch (\Exception $e) {
            error_log("Exception during date parsing for \$this->actualDate: " . $this->actualDate . " - " . $e->getMessage());
            error_log("--- testShouldRunNowHandlesEverydayCorrectly DEBUG END (early return) ---");
            return false;
        }
        
        if (!$triggerDateCarbon) {
            error_log("Error: \$triggerDateCarbon is null after parsing.");
            error_log("--- testShouldRunNowHandlesEverydayCorrectly DEBUG END (early return) ---");
            return false;
        }

        $triggerDateTimeCarbon = $triggerDateCarbon->hour($hour)->minute($minute)->second(0);
        error_log("Scheduled DateTime (\$triggerDateTimeCarbon): " . $triggerDateTimeCarbon->toIso8601String());

        // Calculate current time slot
        $slotMinuteValue = floor($carbonNow->minute / $timerTriggeredByNMins) * $timerTriggeredByNMins; // Renamed for clarity from $slotMinute
        error_log("Calculated \$slotMinuteValue: " . $slotMinuteValue); // Log the float value

        $slotStartTime = $carbonNow->copy()->minute((int)$slotMinuteValue)->second(0)->microsecond(0); // Cast to int
        error_log("Slot Start Time (\$slotStartTime): " . $slotStartTime->toIso8601String());

        $slotEndTime = $slotStartTime->copy()->addMinutes($timerTriggeredByNMins);
        error_log("Slot End Time (\$slotEndTime): " . $slotEndTime->toIso8601String());

        // Timer should run if its scheduled time is within the current slot
        $gteSlotStart = $triggerDateTimeCarbon->gte($slotStartTime);
        $ltSlotEnd = $triggerDateTimeCarbon->lt($slotEndTime);
        error_log("\$triggerDateTimeCarbon >= \$slotStartTime : " . ($gteSlotStart ? 'true' : 'false'));
        error_log("\$triggerDateTimeCarbon < \$slotEndTime : " . ($ltSlotEnd ? 'true' : 'false'));

        $result = $gteSlotStart && $ltSlotEnd;
        error_log("Final \$result: " . ($result ? 'true' : 'false'));
        error_log("--- testShouldRunNowHandlesEverydayCorrectly DEBUG END ---");

        return $result;
    }

    public function __toString(): string
    {
        return "{$this->date} {$this->time}: {$this->request}";
    }
}
