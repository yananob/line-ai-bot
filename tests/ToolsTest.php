<?php

declare(strict_types=1);

namespace MyApp\Tests; // 名前空間を追加

use MyApp\Consts;
// use MyApp\TimerTrigger; // TimerTriggerはTools::convertTriggersToQuickReply内で型宣言されているが、このファイル内で直接newされていないのでuseは不要かも。ただし、可読性のために残すことも可能。
// PHPUnitのTestCaseをuseする
use PHPUnit\Framework\TestCase;
use MyApp\Domain\Bot\Trigger\TimerTrigger; // TimerTriggerクラスの完全修飾名に変更、またはuse文を追加
use MyApp\Tools;


final class ToolsTest extends TestCase // TestCaseの完全修飾名を使用 (useしたのでこれでOK)
{
    // N分によってトリガーされるタイマー (この定数は現在のテストでは使用されていません)
    const TIMER_TRIGGERED_BY_N_MINS = 30;

    protected function setUp(): void
    {
        // セットアップ処理があればここに記述
    }

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
            // TimerTriggerのインスタンス化には、MyApp\Domain\Bot\Trigger\TimerTrigger を使用
            $trigger = new TimerTrigger($line[0], $line[1], $line[2]);
            $trigger->setId($line[3]);
            $triggers[] = $trigger;
        }
        $this->assertEquals($expected, Tools::convertTriggersToQuickReply(Consts::CMD_REMOVE_TRIGGER, $triggers));
    }
}
