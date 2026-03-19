<?php

declare(strict_types=1);

namespace MyApp\Application\CommandHandler;

use MyApp\Domain\Bot\ValueObject\Command;
use MyApp\Domain\Bot\Bot;
use MyApp\Application\BotResponse;
use MyApp\Domain\Bot\Messages;

class HelpHandler implements CommandHandlerInterface
{
    public function canHandle(Command $command): bool
    {
        return $command === Command::ShowHelp;
    }

    public function handle(string $message, Bot $bot, Command $command): BotResponse
    {
        return new BotResponse(Messages::HELP);
    }
}
