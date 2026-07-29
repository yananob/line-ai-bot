<?php declare(strict_types=1);

namespace App\Domain\Bot\ValueObject;

use Carbon\Carbon;
use App\Domain\Bot\Consts;
use App\Domain\Exception\InvalidTriggerScheduleException;

/**
 * Handles the scheduling logic for a trigger, including relative date/time resolution.
 */
class TriggerSchedule
{
    private string $originalDate;
    private string $originalTime;
    private string $resolvedDate;
    private string $resolvedTime;

    /**
     * @param string $date
     * @param string $time
     * @param Carbon|null $now
     * @throws InvalidTriggerScheduleException
     */
    public function __construct(string $date, string $time, ?Carbon $now = null)
    {
        // 値オブジェクトとして、不正な形式でのインスタンス作成を禁止する
        self::validate($date, $time);

        $carbonNow = $now ?? new Carbon(timezone: new \DateTimeZone(Consts::TIMEZONE));
        $targetDateTime = $carbonNow->copy();

        $this->originalDate = $date;
        $this->originalTime = $time;

        // Resolve relative time (e.g., "now +10 mins")
        if (preg_match('/^now \+(\d+) mins$/', $time, $matches)) {
            $targetDateTime->addMinutes((int)$matches[1]);
            $this->resolvedTime = $targetDateTime->format('H:i');
        } else {
            // 秒数付き(HH:MM:SS)をHH:MMに丸める
            if (preg_match('/^([0-1][0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]$/', $time)) {
                $this->resolvedTime = substr($time, 0, 5);
            } else {
                $this->resolvedTime = $time;
            }
        }

        // Resolve relative date (e.g., "today", "tomorrow")
        switch ($date) {
            case 'everyday':
                $this->resolvedDate = 'everyday';
                break;
            case 'today':
                // For 'today', we follow the wrap if the relative time (now +X mins) caused it.
                $this->resolvedDate = $targetDateTime->format('Y/m/d');
                break;
            case 'tomorrow':
                // For 'tomorrow', we use the calendar day after the request time,
                // avoiding a "double jump" if the relative time also crossed midnight.
                $this->resolvedDate = $carbonNow->copy()->addDay()->format('Y/m/d');
                break;
            case 'day after tomorrow':
                $this->resolvedDate = $carbonNow->copy()->addDays(2)->format('Y/m/d');
                break;
            default:
                // Assumes a specific date string or already resolved date
                try {
                    // Try parsing and normalization
                    $this->resolvedDate = Carbon::parse($date, new \DateTimeZone(Consts::TIMEZONE))->format('Y/m/d');
                } catch (\Exception $e) {
                    // Fallback to original if parsing fails
                    $this->resolvedDate = $date;
                }
                break;
        }
    }

    /**
     * トリガースケジュールの形式をバリデートします。
     * 不正な形式の場合、InvalidTriggerScheduleExceptionをスローします。
     *
     * @param string $date
     * @param string $time
     * @throws InvalidTriggerScheduleException
     */
    public static function validate(string $date, string $time): void
    {
        // 1. 時刻のチェック
        $isRelativeTime = (bool)preg_match('/^now \+(\d+) mins$/', $time);
        $isAbsoluteTime = (bool)preg_match('/^([0-1][0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/', $time);

        if (!$isRelativeTime && !$isAbsoluteTime) {
            throw new InvalidTriggerScheduleException("時刻の形式が不正です。'HH:MM'、'HH:MM:SS'、または'now +X mins'を指定してください。入力: '{$time}'");
        }

        // 2. 日付のチェック
        $validRelativeDates = ['everyday', 'today', 'tomorrow', 'day after tomorrow'];
        if (in_array($date, $validRelativeDates, true)) {
            return;
        }

        // 任意の日付文字列の場合は、Carbon::parseが成功するか検証する
        try {
            Carbon::parse($date, new \DateTimeZone(Consts::TIMEZONE));
        } catch (\Exception $e) {
            throw new InvalidTriggerScheduleException("日付の形式が不正です。パース可能な日付文字列を指定してください。入力: '{$date}'", 0, $e);
        }
    }

    public function getResolvedDate(): string
    {
        return $this->resolvedDate;
    }

    public function getResolvedTime(): string
    {
        return $this->resolvedTime;
    }

    public function getOriginalDate(): string
    {
        return $this->originalDate;
    }

    public function getOriginalTime(): string
    {
        return $this->originalTime;
    }

    public function shouldRunNow(int $timerTriggeredByNMins): bool
    {
        $carbonNow = new Carbon(timezone: new \DateTimeZone(Consts::TIMEZONE));

        try {
            list($hour, $minute) = sscanf($this->resolvedTime, "%d:%d");
            if (is_null($hour) || is_null($minute)) {
                return false;
            }
        } catch (\Exception $e) {
            return false;
        }

        try {
            if ($this->resolvedDate === 'everyday') {
                $triggerDateCarbon = $carbonNow->copy()->startOfDay();
            } else {
                // Try to parse resolvedDate
                $triggerDateCarbon = Carbon::parse($this->resolvedDate, new \DateTimeZone(Consts::TIMEZONE))->startOfDay();
            }
        } catch (\Exception $e) {
            return false;
        }

        if (!$triggerDateCarbon) {
            return false;
        }

        $triggerDateTimeCarbon = $triggerDateCarbon->hour($hour)->minute($minute)->second(0);

        // Calculate current time slot
        $slotMinuteValue = floor($carbonNow->minute / $timerTriggeredByNMins) * $timerTriggeredByNMins;
        $slotStartTime = $carbonNow->copy()->minute((int)$slotMinuteValue)->second(0)->microsecond(0);
        $slotEndTime = $slotStartTime->copy()->addMinutes($timerTriggeredByNMins);

        // Timer should run if its scheduled time is within the current slot
        return $triggerDateTimeCarbon->gte($slotStartTime) && $triggerDateTimeCarbon->lt($slotEndTime);
    }

    public function __toString(): string
    {
        return "{$this->resolvedDate} {$this->resolvedTime}";
    }
}
