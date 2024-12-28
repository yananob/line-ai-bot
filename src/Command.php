<?php

declare(strict_types=1);

namespace MyApp;

enum Command: int
{
    case AddOneTimeTrigger = 3;
    case AddDaiyTrigger = 4;
    case Other = 9;
}
