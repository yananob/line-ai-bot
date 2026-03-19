<?php

declare(strict_types=1);

namespace App\Application\CommandHandler;

use App\Application\BotResponse;
use App\Domain\Bot\Bot;

interface PostbackHandlerInterface
{
    public function canHandle(string $command): bool;
    public function handle(array $params, Bot $bot): BotResponse;
}
