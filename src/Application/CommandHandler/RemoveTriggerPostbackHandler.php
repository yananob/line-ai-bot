<?php

declare(strict_types=1);

namespace MyApp\Application\CommandHandler;

use MyApp\Application\BotResponse;
use MyApp\Domain\Bot\Bot;
use MyApp\Domain\Bot\BotRepository;
use MyApp\Domain\Bot\Consts;

class RemoveTriggerPostbackHandler implements PostbackHandlerInterface
{
    private BotRepository $botRepository;

    public function __construct(BotRepository $botRepository)
    {
        $this->botRepository = $botRepository;
    }

    public function canHandle(string $command): bool
    {
        return $command === Consts::CMD_REMOVE_TRIGGER;
    }

    public function handle(array $params, Bot $bot): BotResponse
    {
        $id = $params["id"] ?? "";
        $triggerLabel = $params["trigger"] ?? "";

        $bot->deleteTriggerById($id);
        $this->botRepository->save($bot);

        return new BotResponse("削除しました：" . $triggerLabel);
    }
}
