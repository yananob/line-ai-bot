<?php declare(strict_types=1);

namespace MyApp\Domain\Bot\Trigger;

use MyApp\Domain\Bot\ValueObject\TriggerSchedule;

class TimerTrigger implements Trigger
{
    private ?string $id = null;
    private TriggerSchedule $schedule;
    private string $request;

    /**
     * @param string $date タイマー実行日（JST）
     * @param string $time タイマー実行時間（JST）
     * @param string $request タイマー実行時のリクエスト内容
     */
    public function __construct(string $date, string $time, string $request)
    {
        $this->schedule = new TriggerSchedule($date, $time);
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
        // IMPORTANT: We store the resolved date and time to ensure that
        // relative dates (like 'tomorrow') are fixed upon persistence.
        return [
            'id' => $this->id,
            'event' => $this->getEvent(),
            'date' => $this->schedule->getResolvedDate(),
            'time' => $this->schedule->getResolvedTime(),
            'request' => $this->request,
        ];
    }

    public function getDate(): string
    {
        return $this->schedule->getOriginalDate();
    }

    public function getTime(): string
    {
        // For 'now +X mins', the original code returned the resolved time.
        return $this->schedule->getResolvedTime();
    }

    public function getActualDate(): string
    {
        return $this->schedule->getResolvedDate();
    }

    public function shouldRunNow(int $timerTriggeredByNMins): bool
    {
        return $this->schedule->shouldRunNow($timerTriggeredByNMins);
    }

    public function __toString(): string
    {
        // Maintains consistency with original behavior where time was resolved for 'now +X mins'
        // but date remained as the original input (e.g., 'today', 'tomorrow', or '2023-12-25').
        return "{$this->schedule->getOriginalDate()} {$this->schedule->getResolvedTime()} {$this->request}";
    }
}
