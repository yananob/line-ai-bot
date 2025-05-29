<?php declare(strict_types=1);

namespace MyApp\Domain\Bot\Trigger;

use Carbon\Carbon;

class TimerTrigger implements Trigger
{
    private ?string $id = null;
    private string $date;
    private string $time;
    private string $request;

    public function __construct(string $date, string $time, string $request)
    {
        $this->date = $date;
        $this->time = $time;
        $this->request = $request;
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

    public function shouldRunNow(int $timerTriggeredByNMins): bool
    {
        // If time is relative like "now +X mins", it's not suitable for periodic checks.
        if (str_contains($this->time, 'now +')) {
            return false;
        }

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

        $now = Carbon::now();
        $targetDate = null;

        if ($this->date === 'everyday') {
            $targetDate = Carbon::today()->hour($hour)->minute($minute);
        } elseif ($this->date === 'today') {
            $targetDate = Carbon::today()->hour($hour)->minute($minute);
        } elseif ($this->date === 'tomorrow') {
            // This will be "tomorrow" relative to when this code runs.
            // If the cron runs daily, a trigger for "tomorrow" will become "today" the next day.
            // This logic means it should trigger if checked on the day *before* the intended "tomorrow".
            $targetDate = Carbon::tomorrow()->hour($hour)->minute($minute);
        } else {
            // Specific date 'YYYY-MM-DD'
            try {
                $targetDate = Carbon::parse($this->date)->hour($hour)->minute($minute);
            } catch (\Exception $e) {
                // Invalid date format
                return false;
            }
        }
        
        if (!$targetDate) {
            return false;
        }

        // Check if current time is within the window [targetDate, targetDate + $timerTriggeredByNMins)
        // $now >= $targetDate && $now < $targetDate->copy()->addMinutes($timerTriggeredByNMins)
        return $now->isBetween($targetDate, $targetDate->copy()->addMinutes($timerTriggeredByNMins), true, false);
        // Note: isBetween($a, $b, $equalA, $equalB)
        // $equalA = true means comparison is >= $a
        // $equalB = false means comparison is < $b
        // So, $targetDate <= $now < $targetDate->copy()->addMinutes($timerTriggeredByNMins)
    }

    public function __toString(): string
    {
        return "{$this->date} {$this->time}: {$this->request}";
    }
}
