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
    private ?Logger $logger = null;

    public function __construct(string $date, string $time, string $request)
    {
        $this->logger = new Logger('TimerTrigger');
        $this->logger->log("TimerTrigger constructor called with date: '{$date}', time: '{$time}', request: '{$request}'");
        $carbonNow = new Carbon(timezone: new \DateTimeZone(Consts::TIMEZONE));
        $this->logger->log("TimerTrigger constructor: carbonNow is " . $carbonNow->toString() . " with timezone " . $carbonNow->getTimezone()->getName());

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
        $this->logger->log("TimerTrigger constructor: final this->time is '{$this->time}', final this->actualDate is '{$this->actualDate}'");
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
        $this->logger->log("shouldRunNow: Checking trigger: ID {$this->id}, Date '{$this->date}', Time '{$this->time}', ActualDate '{$this->actualDate}'");
        $carbonNow = new Carbon(timezone: new \DateTimeZone(Consts::TIMEZONE));
        $this->logger->log("shouldRunNow: carbonNow is " . $carbonNow->toString() . " with timezone " . $carbonNow->getTimezone()->getName());

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
                $triggerDateCarbon = Carbon::today(new \DateTimeZone(Consts::TIMEZONE));
            } else {
                // $this->actualDate is expected to be in 'Y/m/d' format
                $triggerDateCarbon = Carbon::parse($this->actualDate, new \DateTimeZone(Consts::TIMEZONE));
            }
        } catch (\Exception $e) {
            // Invalid date format in actualDate
            return false;
        }
        
        if (!$triggerDateCarbon) {
             // Should not happen if logic above is correct
            $this->logger->log("shouldRunNow: triggerDateCarbon is null, returning false.");
            return false;
        }

        $triggerDateTimeCarbon = $triggerDateCarbon->hour($hour)->minute($minute)->second(0);
        if ($triggerDateTimeCarbon) {
            $this->logger->log("shouldRunNow: triggerDateTimeCarbon is " . $triggerDateTimeCarbon->toString() . " with timezone " . $triggerDateTimeCarbon->getTimezone()->getName());
        } else {
            $this->logger->log("shouldRunNow: triggerDateTimeCarbon could not be determined.");
        }

        // Calculate the difference in minutes.
        // A positive value means $triggerDateTimeCarbon is in the future or same minute.
        // A negative value means $triggerDateTimeCarbon is in the past.
        $diffMinutes = $carbonNow->diffInMinutes($triggerDateTimeCarbon, false);
        $this->logger->log("shouldRunNow: diffMinutes is {$diffMinutes}");

        // Trigger if the event is scheduled for the current minute or any minute within the $timerTriggeredByNMins window in the future.
        // For example, if $timerTriggeredByNMins is 5:
        // diffMinutes = 0 (current minute) -> true
        // diffMinutes = 4 (4 minutes in future) -> true
        // diffMinutes = 5 (5 minutes in future) -> false (because it's < $timerTriggeredByNMins)
        // diffMinutes = -1 (1 minute in past) -> false
        $result = $diffMinutes >= 0 && $diffMinutes < $timerTriggeredByNMins;
        $this->logger->log("shouldRunNow: result is " . ($result ? 'true' : 'false'));
        return $result;
    }

    public function __toString(): string
    {
        return "{$this->date} {$this->time}: {$this->request}";
    }
}
