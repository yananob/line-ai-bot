<?php declare(strict_types=1);

namespace MyApp\Domain\Bot\Trigger;

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
}
