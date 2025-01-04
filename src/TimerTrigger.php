<?php

declare(strict_types=1);

namespace MyApp;

use Carbon\Carbon;

class TimerTrigger extends Trigger
{
    private string $id;

    public function __construct(private string $date, private string $time, private string $request)
    {
        $now = new Carbon(timezone: new \DateTimeZone(Consts::TIMEZONE));
        // 実時間に変換
        if (str_contains($time, "今")) {
            preg_match('/今＋([0-9]+)分/', $time, $matches);
            $now->addMinutes((int)$matches[1]);
            $this->setTime($now->format("H:i"));
        }
        // 実日付に変換
        if ($date === "今日") {
            $this->setDate($now->format("Y/m/d"));
        } elseif ($date === "明日") {
            $now->addDay();
            $this->setDate($now->format("Y/m/d"));
        } elseif ($date === "明後日") {
            $now->addDays(2);
            $this->setDate($now->format("Y/m/d"));
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

    public function setDate(string $date): void
    {
        $this->date = $date;
    }
    public function setTime(string $time): void
    {
        $this->time = $time;
    }

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
        return $this->date . " " . $this->time . "：" . $this->request;
    }

    public function shouldRunNow(int $triggerDurationMins): bool
    {
        $triggerDate = $this->getDate();
        if ($triggerDate === "everyday") {
            $triggerDate = "today";
        }
        $triggerTime = new Carbon($triggerDate . " " . $this->getTime(), new \DateTimeZone(Consts::TIMEZONE));
        $diff = (int)$triggerTime->diffInMinutes(new Carbon(timezone: new \DateTimeZone(Consts::TIMEZONE)));
        if (($diff >= $triggerDurationMins) || ($diff < 0)) {
            return false;
        }

        return true;
    }
}
