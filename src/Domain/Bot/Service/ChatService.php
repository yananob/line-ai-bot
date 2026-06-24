<?php

declare(strict_types=1);

namespace App\Domain\Bot\Service;

use App\Domain\Bot\Bot;
use App\Domain\Bot\Messages;
use App\Domain\Conversation\ConversationRepository;
use App\Domain\Bot\ValueObject\Message;

class ChatService
{
    private GptInterface $gpt;
    private ConversationRepository $conversationRepository;
    private ChatPromptService $chatPromptService;
    private ?WebSearchInterface $webSearchTool;

    const RECENT_CONVERSATIONS_COUNT_FOR_GPT = 10;

    public function __construct(
        GptInterface $gpt,
        ConversationRepository $conversationRepository,
        ChatPromptService $chatPromptService,
        ?WebSearchInterface $webSearchTool = null
    ) {
        $this->gpt = $gpt;
        $this->conversationRepository = $conversationRepository;
        $this->chatPromptService = $chatPromptService;
        $this->webSearchTool = $webSearchTool;
    }

    public function generateAnswer(Bot $bot, Message $message): string
    {
        $recentConversations = $this->conversationRepository->findByBotId(
            $bot->getId(),
            self::RECENT_CONVERSATIONS_COUNT_FOR_GPT
        );

        $webSearchResults = null;
        if ($this->shouldPerformWebSearch($message->getContent())) {
            if ($this->webSearchTool instanceof WebSearchInterface) {
                $webSearchResults = $this->webSearchTool->search($message->getContent(), 5);
            } else {
                $webSearchResults = "Error: Web search tool is not configured properly or failed to initialize.";
            }
        }

        $configRequests = $bot->getConfigRequests(usePersonal: true, useDefault: true);

        return $this->gpt->getAnswer(
            context: $this->chatPromptService->generateContext(
                $bot,
                $recentConversations,
                $configRequests,
                $webSearchResults
            ),
            message: $message->getContent(),
        );
    }

    private function shouldPerformWebSearch(string $messageContent): bool
    {
        $response = trim($this->gpt->getAnswer(
            context: Messages::PROMPT_JUDGE_WEB_SEARCH,
            message: $messageContent,
        ));
        return $response === "はい";
    }
}
