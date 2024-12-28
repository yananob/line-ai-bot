<?php

declare(strict_types=1);

namespace MyApp;

enum Command: string
{
    case AddOneTimeTrigger = "add_one_time_trigger";
    case AddDaiyTrigger = "add_daily_trigger";
    case Other = "other";
}
