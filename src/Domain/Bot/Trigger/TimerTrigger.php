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
        $carbonNow = new Carbon(timezone: new \DateTimeZone(Consts::TIMEZONE));

        try {
            list($hour, $minute) = sscanf($this->time, "%d:%d");
            if (is_null($hour) || is_null($minute)) {
                // Invalid time format
                return false;
            }
        } catch (\Exception $e) {
            // Parsing failed
            return false;
        }

        $triggerDateCarbon = null;
        try {
            if ($this->actualDate === 'everyday') {
                $triggerDateCarbon = $carbonNow->copy()->startOfDay();
            } else {
                // $this->actualDate is expected to be in 'Y/m/d' format
                $triggerDateCarbon = Carbon::parse($this->actualDate, new \DateTimeZone(Consts::TIMEZONE))->startOfDay();
            }
        } catch (\Exception $e) {
            // Invalid date format in actualDate
            return false;
        }
        
        if (!$triggerDateCarbon) {
             // Should not happen if logic above is correct
            return false;
        }

        $triggerDateTimeCarbon = $triggerDateCarbon->hour($hour)->minute($minute)->second(0);

        // Calculate current time slot
        $slotMinute = floor($carbonNow->minute / $timerTriggeredByNMins) * $timerTriggeredByNMins;
        $slotStartTime = $carbonNow->copy()->minute($slotMinute)->second(0)->microsecond(0);
        $slotEndTime = $slotStartTime->copy()->addMinutes($timerTriggeredByNMins);

        // Timer should run if its scheduled time is within the current slot
        $result = $triggerDateTimeCarbon->gte($slotStartTime) &&
                  $triggerDateTimeCarbon->lt($slotEndTime);

        return $result;
    }

    public function __toString(): string
    {
        return "{$this->date} {$this->time}: {$this->request}";
    }
}
