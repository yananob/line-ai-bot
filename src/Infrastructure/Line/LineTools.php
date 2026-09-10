<?php

declare(strict_types=1);

namespace App\Infrastructure\Line;

use App\Domain\Bot\Consts;
use App\Domain\Bot\Trigger\Trigger;

/**
 * LINE Messaging API表示用ユーティリティクラス。
 */
class LineTools
{
    /**
     * トリガー一覧をLINEクイックリプライアクション配列に変換します。
     *
     * @param string $command コマンド名
     * @param Trigger[] $triggers トリガーオブジェクト配列
     * @return array<int, array<string, mixed>> クイックリプライアクション要素の配列
     */
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
                    "displayText" => (Consts::CMD_LABELS[$command] ?? "") . "：{$trigger}",
                ],
            ];
        }

        return $result;
    }
}
