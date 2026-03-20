<?php

declare(strict_types=1);

namespace App\Application\CommandHandler;

use App\Domain\Bot\BotRepository;
use App\Domain\Conversation\ConversationRepository;
use App\Domain\Bot\Service\ChatPromptService;
use App\Domain\Bot\Service\CommandAndTriggerService;
use App\Domain\Bot\Service\WebSearchInterface;
use yananob\MyTools\Gpt;

class CommandHandlerFactory
{
    /**
     * @return CommandHandlerInterface[]
     */
    public static function createMessageHandlers(
        CommandAndTriggerService $commandAndTriggerService,
        BotRepository $botRepository,
        Gpt $gpt,
        ConversationRepository $conversationRepository,
        ChatPromptService $chatPromptService,
        ?WebSearchInterface $webSearchTool
    ): array {
        return [
            new HelpHandler(),
            new AddTriggerHandler($commandAndTriggerService, $botRepository),
            new RemoveTriggerHandler(),
            new DefaultChatHandler($gpt, $conversationRepository, $chatPromptService, $webSearchTool)
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
