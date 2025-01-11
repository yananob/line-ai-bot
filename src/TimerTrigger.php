<?php

declare(strict_types=1);

namespace MyApp;

use Carbon\Carbon;

class TimerTrigger extends Trigger
{
    private string $id;
    private string $actualDate;

    public function __construct(private string $date, private string $time, private string $request)
    {
        $now = new Carbon(timezone: new \DateTimeZone(Consts::TIMEZONE));
        // 実時間に変換
        if (str_contains($time, "now")) {
            preg_match('/now \+([0-9]+) mins/', $time, $matches);
            $now->addMinutes((int)$matches[1]);
            $this->time = $now->format("H:i");
        }
        // 実日付に変換
        switch ($this->date) {
            case 'everyday':
                $this->actualDate = $now->format("Y/m/d");
                break;

            case 'today':
                $this->actualDate = $now->format("Y/m/d");
                $this->date = $this->actualDate;
                break;

            case 'tomorrow':
                $now->addDay();
                $this->actualDate = $now->format("Y/m/d");
                $this->date = $this->actualDate;
                break;

            case 'day after tomorrow':
                $now->addDays(2);
                $this->actualDate = $now->format("Y/m/d");
                $this->date = $this->actualDate;
                break;

            default:
                $this->actualDate = $this->date;
                break;
        }
    }

    public function getEvent(): string
    {
        return "timer";
    }

    public function getDate(): string
    {
        return $this->date;
    }
    public function getTime(): string
    {
        return $this->time;
    }
    public function getRequest(): string
    {
        return $this->request;
    }

    // private function __setDate(string $date): void
    // {
    //     $this->date = $date;
    // }
    // public function setTime(string $time): void
    // {
    //     $this->time = $time;
    // }

    public function getId(): string
    {
        return $this->id;
    }
    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function __toString(): string
    {
        $outputDate = $this->date;
        $outputDate = str_replace(["everyday"], ["毎日"], $outputDate);
        return $outputDate . " " . $this->time . " " . $this->request;
    }

    public function shouldRunNow(int $triggerDurationMins): bool
    {
        $triggerTime = new Carbon($this->actualDate . " " . $this->getTime() . ":00", new \DateTimeZone(Consts::TIMEZONE));
        $diff = (new Carbon(timezone: new \DateTimeZone(Consts::TIMEZONE)))->diffInMinutes($triggerTime);
        if (($diff >= $triggerDurationMins) || ($diff < 0)) {
            return false;
        }

        return true;
    }
}
