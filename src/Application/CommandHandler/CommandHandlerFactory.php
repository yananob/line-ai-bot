<?php

declare(strict_types=1);

namespace App\Application\CommandHandler;

use App\Domain\Bot\BotRepository;
use App\Domain\Conversation\ConversationRepository;
use App\Domain\Bot\Service\ChatService;
use App\Domain\Bot\Service\CommandAndTriggerService;

class CommandHandlerFactory
{
    /**
     * @return CommandHandlerInterface[]
     */
    public static function createMessageHandlers(
        CommandAndTriggerService $commandAndTriggerService,
        BotRepository $botRepository,
        ChatService $chatService,
        ConversationRepository $conversationRepository
    ): array {
        return [
            new HelpHandler(),
            new AddTriggerHandler($commandAndTriggerService, $botRepository),
            new RemoveTriggerHandler(),
            new DefaultChatHandler($chatService, $conversationRepository)
        ];
    }

    /**
     * @return PostbackHandlerInterface[]
     */
    public static function createPostbackHandlers(
        BotRepository $botRepository
    ): array {
        return [
            new RemoveTriggerPostbackHandler($botRepository)
        ];
    }
}
