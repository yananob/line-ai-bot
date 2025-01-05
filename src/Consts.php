<?php

declare(strict_types=1);

namespace MyApp;

class Consts
{
    const TIMEZONE = "Asia/Tokyo";

    const CMD_REMOVE_TRIGGER = "delete_trigger";

    const CMD_LABELS = [
        self::CMD_REMOVE_TRIGGER => "削除",
    ];
}
