<?php

declare(strict_types=1);

namespace MyApp;

enum Command: string
{
    // case AddRequest = "1";
    // case AddCharacteristic = "2";
    case AddOneTimeTrigger = "3";
    case AddDailyTrigger = "4";
    case RemoveTrigger = "5";
    case ShowHelp = "8";
    case Other = "9";
}
