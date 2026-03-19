<?php

declare(strict_types=1);

namespace MyApp\Application\CommandHandler;

use MyApp\Domain\Bot\BotRepository;
use MyApp\Domain\Conversation\ConversationRepository;
use MyApp\Domain\Bot\Service\ChatPromptService;
use MyApp\Domain\Bot\Service\CommandAndTriggerService;
use MyApp\Domain\Bot\Service\WebSearchInterface;
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
