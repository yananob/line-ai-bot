<?php declare(strict_types=1);

namespace App\Domain\Bot\Trigger;

use App\Domain\Exception\InvalidTriggerScheduleException;

class TriggerFactory
{
    /**
     * @param string $id
     * @param array<string, mixed> $data
     * @return Trigger|null
     */
    public static function fromArray(string $id, array $data): ?Trigger
    {
        if (isset($data['event']) && $data['event'] === TimerTrigger::EVENT_TIMER) {
            $date = (string)($data['date'] ?? '');
            $time = (string)($data['time'] ?? '');
            $request = (string)($data['request'] ?? '');
            try {
                $trigger = new TimerTrigger($date, $time, $request);
                $trigger->setId($id);
                return $trigger;
            } catch (InvalidTriggerScheduleException $e) {
                // 不正なスケジュール設定を持つトリガーを検出した場合、ログに警告を出力し、nullを返してスキップできるようにする
                error_log("警告：不正なトリガースケジュール（ID: {$id}）の復元に失敗したため、スキップします。詳細: " . $e->getMessage());
                return null;
            }
        }

        return null;
    }
}
