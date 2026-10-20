<?php

declare(strict_types=1);

namespace App\Application\CommandHandler;

use App\Domain\Bot\ValueObject\Command;
use App\Domain\Bot\Bot;
use App\Domain\Bot\BotRepository;
use App\Domain\Bot\Service\CommandAndTriggerService;
use App\Application\BotResponse;
use App\Domain\Bot\Messages;
use App\Domain\Bot\ValueObject\Message;
use App\Domain\Exception\InvalidTriggerScheduleException;

class AddTriggerHandler implements CommandHandlerInterface
{
    private CommandAndTriggerService $commandAndTriggerService;
    private BotRepository $botRepository;

    public function __construct(
        CommandAndTriggerService $commandAndTriggerService,
        BotRepository $botRepository
    ) {
        $this->commandAndTriggerService = $commandAndTriggerService;
        $this->botRepository = $botRepository;
    }

    public function canHandle(Command $command): bool
    {
        return $command === Command::AddOneTimeTrigger || $command === Command::AddDailyTrigger;
    }

    public function handle(Message $message, Bot $bot, Command $command): BotResponse
    {
        try {
            if ($command === Command::AddOneTimeTrigger) {
                $trigger = $this->commandAndTriggerService->generateOneTimeTrigger($message->getContent());
            } else {
                $trigger = $this->commandAndTriggerService->generateDailyTrigger($message->getContent());
            }

            if ($trigger->getDate() === '不明' || $trigger->getTime() === '不明') {
                return new BotResponse(Messages::TRIGGER_RETRY_PROMPT);
            }

            $bot->addTrigger($trigger);
            $this->botRepository->save($bot);

            return new BotResponse("タイマーを追加しました：" . $trigger);
        } catch (InvalidTriggerScheduleException $e) {
            return new BotResponse(Messages::TRIGGER_RETRY_PROMPT);
        }
    }
}
