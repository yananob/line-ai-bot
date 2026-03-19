<?php

declare(strict_types=1);

namespace MyApp\Application\CommandHandler;

use MyApp\Domain\Bot\ValueObject\Command;
use MyApp\Domain\Bot\Bot;
use MyApp\Application\BotResponse;
use MyApp\Domain\Bot\Consts;
use MyApp\Infrastructure\Line\LineTools;

class RemoveTriggerHandler implements CommandHandlerInterface
{
    public function canHandle(Command $command): bool
    {
        return $command === Command::RemoveTrigger;
    }

    public function handle(string $message, Bot $bot, Command $command): BotResponse
    {
        $answer = "どのタイマーを止めますか？";
        $quickReply = LineTools::convertTriggersToQuickReply(Consts::CMD_REMOVE_TRIGGER, $bot->getTriggers());

        return new BotResponse($answer, $quickReply);
    }
}
