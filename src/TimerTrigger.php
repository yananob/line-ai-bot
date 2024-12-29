<?php

declare(strict_types=1);

namespace MyApp;

class TimerTrigger extends Trigger
{
    public function __construct(private string $date, private string $time, private string $request) {}

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
}
