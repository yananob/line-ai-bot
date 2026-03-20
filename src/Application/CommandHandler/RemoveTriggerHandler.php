<?php

declare(strict_types=1);

namespace App\Application\CommandHandler;

use App\Domain\Bot\ValueObject\Command;
use App\Domain\Bot\Bot;
use App\Application\BotResponse;
use App\Domain\Bot\Consts;
use App\Infrastructure\Line\LineTools;

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
