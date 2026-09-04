<?php

declare(strict_types=1);

namespace App\Domain\Bot;

/**
 * ボットドメインに関する定数クラス。
 */
class Consts
{
    /** デフォルトタイムゾーン */
    public const TIMEZONE = "Asia/Tokyo";

    /** トリガー削除コマンド名 */
    public const CMD_REMOVE_TRIGGER = "delete_trigger";

    /** コマンドラベルマッピング */
    public const CMD_LABELS = [
        self::CMD_REMOVE_TRIGGER => "削除",
    ];
}
