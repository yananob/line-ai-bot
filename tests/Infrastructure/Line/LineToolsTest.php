<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Line;

use App\Domain\Bot\Consts;
use App\Domain\Bot\Trigger\TimerTrigger;
use App\Infrastructure\Line\LineTools;
use PHPUnit\Framework\TestCase;

final class LineToolsTest extends TestCase
{
    public function test_トリガーをクイックリプライに変換する(): void
    {
        $input = [
            [
                "2025/01/01",
                "09:00",
                "おはようと送って",
                "ID001",
            ],
        ];
        $expected = [
            [
                "type" => "action",
                "action" => [
                    "type" => "postback",
                    "label" => "2025/01/01 09:00 お…",
                    "displayText" => "削除：2025/01/01 09:00 おはようと送って",
                    "data" => "command=delete_trigger&id=ID001&trigger=2025/01/01 09:00 おはようと送って",
                ]
            ]
        ];

        $triggers = [];
        foreach ($input as $line) {
            $trigger = new TimerTrigger($line[0], $line[1], $line[2]);
            $trigger->setId($line[3]);
            $triggers[] = $trigger;
        }
        $this->assertEquals($expected, LineTools::convertTriggersToQuickReply(Consts::CMD_REMOVE_TRIGGER, $triggers));
    }
}
