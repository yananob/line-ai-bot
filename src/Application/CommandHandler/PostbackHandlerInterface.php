<?php

declare(strict_types=1);

namespace MyApp\Application\CommandHandler;

use MyApp\Application\BotResponse;
use MyApp\Domain\Bot\Bot;

interface PostbackHandlerInterface
{
    public function canHandle(string $command): bool;
    public function handle(array $params, Bot $bot): BotResponse;
}
