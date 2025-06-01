<?php declare(strict_types=1);

namespace MyApp\Domain\Bot\Trigger;

use Carbon\Carbon;
use MyApp\Consts;
use yananob\MyTools\Logger;

class TimerTrigger implements Trigger
{
    private ?string $id = null;
    private string $date;
    private string $time;
    private string $request;
    private string $actualDate;

    public function __construct(string $date, string $time, string $request)
    {
        $logger = new Logger("TimerTrigger::__construct");
        $logger->log("Entry. Input date: '{$date}', Input time: '{$time}', Request: '{$request}'");

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
        $logger->log("Finalized. actualDate: '{$this->actualDate}', time: '{$this->time}'");
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
        $logger = new Logger("TimerTrigger::shouldRunNow");
        $logger->log("Entry. timerTriggeredByNMins: {$timerTriggeredByNMins}, this->actualDate: '{$this->actualDate}', this->time: '{$this->time}'");

        $carbonNow = new Carbon(timezone: new \DateTimeZone(Consts::TIMEZONE));
        $logger->log("Current time (\$carbonNow): " . $carbonNow->toDateTimeString() . " (Timezone: " . $carbonNow->getTimezone()->getName() . ")");

        try {
            list($hour, $minute) = sscanf($this->time, "%d:%d");
            if (is_null($hour) || is_null($minute)) {
                $logger->log("Invalid time format in this->time: '{$this->time}'. Exiting.");
                return false;
            }
        } catch (\Exception $e) {
            $logger->log("Exception during sscanf parsing of this->time: '{$this->time}'. Message: " . $e->getMessage() . ". Exiting.");
            return false;
        }

        $triggerDateCarbon = null;
        try {
            if ($this->actualDate === 'everyday') {
                $triggerDateCarbon = Carbon::today(new \DateTimeZone(Consts::TIMEZONE));
            } else {
                // $this->actualDate is expected to be in 'Y/m/d' format
                $triggerDateCarbon = Carbon::parse($this->actualDate, new \DateTimeZone(Consts::TIMEZONE));
            }
        } catch (\Exception $e) {
            $logger->log("Exception during Carbon::parse or Carbon::today for actualDate: '{$this->actualDate}'. Message: " . $e->getMessage() . ". Exiting.");
            return false;
        }
        
        if (!$triggerDateCarbon) {
             // Should not happen if logic above is correct, but good to log
            $logger->log("triggerDateCarbon is null after attempting to parse actualDate: '{$this->actualDate}'. Exiting.");
            return false;
        }

        $triggerDateTimeCarbon = $triggerDateCarbon->hour($hour)->minute($minute)->second(0);
        $logger->log("Trigger target time (\$triggerDateTimeCarbon): " . $triggerDateTimeCarbon->toDateTimeString() . " (Timezone: " . $triggerDateTimeCarbon->getTimezone()->getName() . ")");

        // Calculate the difference in minutes.
        // $carbonNow->diffInMinutes($triggerDateTimeCarbon, false)
        // If $triggerDateTimeCarbon is in the future, $diffMinutes will be negative.
        // If $triggerDateTimeCarbon is in the past, $diffMinutes will be positive.
        $diffMinutes = $carbonNow->diffInMinutes($triggerDateTimeCarbon, false);
        $logger->log("Calculated \$diffMinutes: {$diffMinutes}");

        // Original logic: run if $triggerDateTimeCarbon is in the past (positive $diffMinutes)
        // or current minute (0 $diffMinutes), and not too far in the past.
        $result = ($diffMinutes >= 0 && $diffMinutes < $timerTriggeredByNMins);
        $logger->log("Result: " . ($result ? 'true' : 'false'));
        return $result;
    }

    public function __toString(): string
    {
        return "{$this->date} {$this->time}: {$this->request}";
    }
}
