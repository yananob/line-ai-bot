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
        $trimmedDate = trim($date);
        $trimmedTime = trim($time);

        // 値オブジェクトとして、不正な形式でのインスタンス作成を禁止する
        self::validate($trimmedDate, $trimmedTime);

        $carbonNow = $now ?? new Carbon(timezone: new \DateTimeZone(Consts::TIMEZONE));
        $targetDateTime = $carbonNow->copy();

        $this->originalDate = $trimmedDate;
        $this->originalTime = $trimmedTime;

        // Resolve relative time (e.g., "now +10 mins")
        if (preg_match('/^now \+(\d+) mins$/', $trimmedTime, $matches)) {
            $targetDateTime->addMinutes((int)$matches[1]);
            $this->resolvedTime = $targetDateTime->format('H:i');
        } else {
            // 時刻(H:i:s または H:i または 1桁時間)を HH:MM に正規化
            if (preg_match('/^([0-1]?[0-9]|2[0-3]):([0-5][0-9])(:[0-5][0-9])?$/', $trimmedTime, $timeMatches)) {
                $this->resolvedTime = sprintf('%02d:%02d', (int)$timeMatches[1], (int)$timeMatches[2]);
            } else {
                $this->resolvedTime = $trimmedTime;
            }
        }

        // Resolve relative date (e.g., "today", "tomorrow")
        switch ($trimmedDate) {
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
                    $this->resolvedDate = Carbon::parse($trimmedDate, new \DateTimeZone(Consts::TIMEZONE))->format('Y/m/d');
                } catch (\Exception $e) {
                    // Fallback to original if parsing fails
                    $this->resolvedDate = $trimmedDate;
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
        $trimmedDate = trim($date);
        $trimmedTime = trim($time);

        // 1. 時刻のチェック
        $isRelativeTime = (bool)preg_match('/^now \+(\d+) mins$/', $trimmedTime);
        $isAbsoluteTime = (bool)preg_match('/^([0-1]?[0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/', $trimmedTime);

        if (!$isRelativeTime && !$isAbsoluteTime) {
            throw new InvalidTriggerScheduleException("時刻の形式が不正です。'HH:MM'、'HH:MM:SS'、または'now +X mins'を指定してください。入力: '{$time}'");
        }

        // 2. 日付のチェック
        $validRelativeDates = ['everyday', 'today', 'tomorrow', 'day after tomorrow'];
        if (in_array($trimmedDate, $validRelativeDates, true)) {
            return;
        }

        // 任意の日付文字列の場合は、Carbon::parseが成功するか検証する
        try {
            Carbon::parse($trimmedDate, new \DateTimeZone(Consts::TIMEZONE));
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

    public function shouldRunNow(int $timerTriggeredByNMins, ?Carbon $now = null): bool
    {
        // ゼロ以下の間隔指定によるゼロ除算エラーを防止
        if ($timerTriggeredByNMins <= 0) {
            return false;
        }

        $carbonNow = $now ?? new Carbon(timezone: new \DateTimeZone(Consts::TIMEZONE));

        // resolvedTime は常に 'HH:MM' 形式
        $timeParts = explode(':', $this->resolvedTime, 2);
        if (count($timeParts) < 2 || !is_numeric($timeParts[0]) || !is_numeric($timeParts[1])) {
            return false;
        }

        $hour = (int)$timeParts[0];
        $minute = (int)$timeParts[1];

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
