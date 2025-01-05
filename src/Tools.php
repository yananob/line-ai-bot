<?php

declare(strict_types=1);

namespace MyApp;

class Tools
{
    public static function convertTriggersToQuickReply(array $triggers): array
    {
        $result = [];

        foreach ($triggers as $trigger) {
            $result[] = [
                "type" => "action",
                "action" => [
                    "type" => "postback",
                    "label" => mb_strimwidth("{$trigger}", 0, 20, "…"),
                    "data" => $trigger->getId(),
                    "displayText" => "{$trigger}",
                ],
            ];
        }

        return $result;
    }
}
