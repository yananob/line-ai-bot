<?php

declare(strict_types=1);

namespace MyApp;

class BotConfigsStore
{

    public function __construct() {}

    public function exists(string $targetId): bool {}

    public function get(string $targetId): BotConfig {}
    public function getDefault(): BotConfig {}
}
