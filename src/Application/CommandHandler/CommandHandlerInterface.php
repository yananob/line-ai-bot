<?php

declare(strict_types=1);

namespace App\Application\CommandHandler;

use App\Domain\Bot\ValueObject\Command;
use App\Domain\Bot\Bot;
use App\Application\BotResponse;

interface CommandHandlerInterface
{
    public function canHandle(Command $command): bool;
    public function handle(string $message, Bot $bot, Command $command): BotResponse;
}
