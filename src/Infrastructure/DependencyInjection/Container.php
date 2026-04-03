<?php

declare(strict_types=1);

namespace App\Infrastructure\DependencyInjection;

use App\Application\ChatApplicationService;
use App\Application\CommandHandler\CommandHandlerFactory;
use App\Domain\Bot\Bot;
use App\Domain\Bot\Service\ChatPromptService;
use App\Domain\Bot\Service\CommandAndTriggerService;
use App\Infrastructure\Gpt\OpenAiGptClient;
use App\Infrastructure\Line\LineClient;
use App\Infrastructure\Persistence\Firestore\FirestoreBotRepository;
use App\Infrastructure\Persistence\Firestore\FirestoreConversationRepository;
use App\Infrastructure\Search\OpenAIWebSearchTool;
use OpenAI;

class Container
{
    private ?FirestoreBotRepository $botRepository = null;
    private ?FirestoreConversationRepository $conversationRepository = null;
    private ?ChatPromptService $chatPromptService = null;
    private ?OpenAiGptClient $gptClient = null;
    private ?CommandAndTriggerService $commandAndTriggerService = null;
    private ?OpenAIWebSearchTool $webSearchTool = null;
    private ?LineClient $lineClient = null;
    private ?\App\Domain\Config\ConfigRepository $configRepository = null;

    public function __construct(private bool $isLocal)
    {
    }

    public function getConfigRepository(): \App\Domain\Config\ConfigRepository
    {
        if ($this->configRepository === null) {
            $this->configRepository = new \App\Infrastructure\Persistence\Firestore\FirestoreConfigRepository();
        }
        return $this->configRepository;
    }

    public function getBotRepository(): FirestoreBotRepository
    {
        if ($this->botRepository === null) {
            $this->botRepository = new FirestoreBotRepository();
        }
        return $this->botRepository;
    }

    public function getConversationRepository(): FirestoreConversationRepository
    {
        if ($this->conversationRepository === null) {
            $this->conversationRepository = new FirestoreConversationRepository();
        }
        return $this->conversationRepository;
    }

    public function getChatPromptService(): ChatPromptService
    {
        if ($this->chatPromptService === null) {
            $this->chatPromptService = new ChatPromptService();
        }
        return $this->chatPromptService;
    }

    public function getGptClient(): OpenAiGptClient
    {
        if ($this->gptClient === null) {
            $openaiApiKey = getenv("OPENAI_KEY_LINE_AI_BOT") ?: 'dummy';
            $openaiClient = OpenAI::client($openaiApiKey);
            $this->gptClient = new OpenAiGptClient($openaiClient, "gpt-4o");
        }
        return $this->gptClient;
    }

    public function getCommandAndTriggerService(): CommandAndTriggerService
    {
        if ($this->commandAndTriggerService === null) {
            $this->commandAndTriggerService = new CommandAndTriggerService($this->getGptClient());
        }
        return $this->commandAndTriggerService;
    }

    public function getWebSearchTool(): ?OpenAIWebSearchTool
    {
        if ($this->webSearchTool === null) {
            $openaiApiKey = getenv("OPENAI_KEY_LINE_AI_BOT") ?: 'dummy';
            if ($openaiApiKey !== 'dummy') {
                $openaiClient = OpenAI::client($openaiApiKey);
                try {
                    $this->webSearchTool = new OpenAIWebSearchTool($openaiClient, "gpt-5-mini");
                } catch (\Exception $e) {
                    // Log error if needed, but return null as it's optional
                    error_log("Failed to initialize WebSearchTool: " . $e->getMessage());
                }
            }
        }
        return $this->webSearchTool;
    }

    public function getLineClient(): LineClient
    {
        if ($this->lineClient === null) {
            $lineConfig = json_decode(getenv("LINE_TOKENS_N_TARGETS") ?: '{"tokens": [], "target_ids": []}', true);
            $this->lineClient = new LineClient($lineConfig["tokens"], $lineConfig["target_ids"]);
        }
        return $this->lineClient;
    }

    public function createConfigApplicationService(): \App\Application\Config\ConfigApplicationService
    {
        // Use /tmp for GCF compatibility as the filesystem is read-only.
        $cachePath = sys_get_temp_dir() . '/bladeone_cache';
        return new \App\Application\Config\ConfigApplicationService(
            $this->getConfigRepository(),
            __DIR__ . '/../../../views',
            $cachePath
        );
    }

    public function createChatApplicationService(Bot $bot): ChatApplicationService
    {
        $messageHandlers = CommandHandlerFactory::createMessageHandlers(
            $this->getCommandAndTriggerService(),
            $this->getBotRepository(),
            $this->getGptClient(),
            $this->getConversationRepository(),
            $this->getChatPromptService(),
            $this->getWebSearchTool()
        );
        $postbackHandlers = CommandHandlerFactory::createPostbackHandlers($this->getBotRepository());

        return new ChatApplicationService(
            $bot,
            $this->getCommandAndTriggerService(),
            $messageHandlers,
            $postbackHandlers
        );
    }
}
