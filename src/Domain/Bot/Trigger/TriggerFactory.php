<?php declare(strict_types=1);

namespace App\Domain\Bot\Trigger;

class TriggerFactory
{
    /**
     * @param string $id
     * @param array<string, mixed> $data
     * @return Trigger|null
     */
    public static function fromArray(string $id, array $data): ?Trigger
    {
        if (isset($data['event']) && $data['event'] === 'timer') {
            $date = (string)($data['date'] ?? '');
            $time = (string)($data['time'] ?? '');
            $request = (string)($data['request'] ?? '');
            $trigger = new TimerTrigger($date, $time, $request);
            $trigger->setId($id);
            return $trigger;
        }

        return null;
    }
}
