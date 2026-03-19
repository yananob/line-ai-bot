<?php

declare(strict_types=1);

namespace MyApp\Application\CommandHandler;

use MyApp\Domain\Bot\ValueObject\Command;
use MyApp\Domain\Bot\Bot;
use MyApp\Application\BotResponse;

interface CommandHandlerInterface
{
    public function canHandle(Command $command): bool;
    public function handle(string $message, Bot $bot, Command $command): BotResponse;
}
