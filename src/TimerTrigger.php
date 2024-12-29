<?php

declare(strict_types=1);

namespace MyApp;

class TimerTrigger extends Trigger
{
    private string $id;

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
}
