<?php

declare(strict_types=1);

namespace Tests\Application\CommandHandler;

use App\Application\CommandHandler\CommandHandlerFactory;
use App\Application\CommandHandler\CommandHandlerInterface;
use App\Application\CommandHandler\PostbackHandlerInterface;
use App\Domain\Bot\BotRepository;
use App\Domain\Conversation\ConversationRepository;
use App\Domain\Bot\Service\ChatPromptService;
use App\Domain\Bot\Service\CommandAndTriggerService;
use App\Domain\Bot\Service\WebSearchInterface;
use App\Domain\Bot\Service\GptInterface;
use PHPUnit\Framework\TestCase;

final class CommandHandlerFactoryTest extends TestCase
{
    public function test_createMessageHandlers_returns_expected_handlers(): void
    {
        $commandAndTriggerService = $this->createMock(CommandAndTriggerService::class);
        $botRepository = $this->createMock(BotRepository::class);
        $gpt = $this->createMock(GptInterface::class);
        $conversationRepository = $this->createMock(ConversationRepository::class);
        $chatPromptService = $this->createMock(ChatPromptService::class);
        $webSearchTool = $this->createMock(WebSearchInterface::class);

        $handlers = CommandHandlerFactory::createMessageHandlers(
            $commandAndTriggerService,
            $botRepository,
            $gpt,
            $conversationRepository,
            $chatPromptService,
            $webSearchTool
        );

        $this->assertIsArray($handlers);
        $this->assertCount(4, $handlers);

        $this->assertInstanceOf(\App\Application\CommandHandler\HelpHandler::class, $handlers[0]);
        $this->assertInstanceOf(\App\Application\CommandHandler\AddTriggerHandler::class, $handlers[1]);
        $this->assertInstanceOf(\App\Application\CommandHandler\RemoveTriggerHandler::class, $handlers[2]);
        $this->assertInstanceOf(\App\Application\CommandHandler\DefaultChatHandler::class, $handlers[3]);
    }

    public function test_createPostbackHandlers_returns_expected_handlers(): void
    {
        $botRepository = $this->createMock(BotRepository::class);

        $handlers = CommandHandlerFactory::createPostbackHandlers($botRepository);

        $this->assertIsArray($handlers);
        $this->assertCount(1, $handlers);
        foreach ($handlers as $handler) {
            $this->assertInstanceOf(PostbackHandlerInterface::class, $handler);
        }
    }
}
