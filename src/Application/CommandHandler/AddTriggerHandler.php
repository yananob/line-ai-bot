<?php

declare(strict_types=1);

namespace MyApp\Application\CommandHandler;

use MyApp\Domain\Bot\ValueObject\Command;
use MyApp\Domain\Bot\Bot;
use MyApp\Domain\Bot\BotRepository;
use MyApp\Domain\Bot\Service\CommandAndTriggerService;
use MyApp\Application\BotResponse;

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

    public function handle(string $message, Bot $bot, Command $command): BotResponse
    {
        if ($command === Command::AddOneTimeTrigger) {
            $trigger = $this->commandAndTriggerService->generateOneTimeTrigger($message);
        } else {
            $trigger = $this->commandAndTriggerService->generateDailyTrigger($message);
        }

        $bot->addTrigger($trigger);
        $this->botRepository->save($bot);

        return new BotResponse("タイマーを追加しました：" . $trigger);
    }
}
