<?php

declare(strict_types=1);

namespace MyApp;

class Tools
{
    public static function convertTriggersToQuickReply(string $command, array $triggers): array
    {
        $result = [];

        foreach ($triggers as $trigger) {
            $result[] = [
                "type" => "action",
                "action" => [
                    "type" => "postback",
                    "label" => mb_strimwidth("{$trigger}", 0, 20, "…"),
                    "data" => "command={$command}&id=" . $trigger->getId() . "&trigger={$trigger}",
                    "displayText" => Consts::CMD_LABELS[$command] . "：{$trigger}",
                ],
            ];
        }

        return $result;
    }
}
