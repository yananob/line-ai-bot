<?php

declare(strict_types=1);

namespace App\Application\CommandHandler;

use App\Domain\Bot\ValueObject\Command;
use App\Domain\Bot\Bot;
use App\Application\BotResponse;
use App\Domain\Bot\Messages;
use App\Domain\Bot\ValueObject\Message;

class HelpHandler implements CommandHandlerInterface
{
    public function canHandle(Command $command): bool
    {
        return $command === Command::ShowHelp;
    }

    public function handle(Message $message, Bot $bot, Command $command): BotResponse
    {
        return new BotResponse(Messages::HELP);
    }
}
