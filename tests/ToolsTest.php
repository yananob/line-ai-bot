<?php

declare(strict_types=1);

use MyApp\LineWebhookMessage;
use MyApp\TimerTrigger;
use MyApp\Tools;

final class ToolsTest extends PHPUnit\Framework\TestCase
{
    const TIMER_TRIGGERED_BY_N_MINS = 30;

    protected function setUp(): void {}

    public function testConvertTriggersToQuickReply()
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
                    "displayText" => "2025/01/01 09:00 おはようと送って",
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
        $this->assertEquals($expected, Tools::convertTriggersToQuickReply(LineWebhookMessage::CMD_REMOVE_TRIGGER, $triggers));
    }
}
