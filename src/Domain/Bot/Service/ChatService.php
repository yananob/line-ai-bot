<?php

declare(strict_types=1);

namespace App\Domain\Bot\Service;

use App\Domain\Bot\Bot;
use App\Domain\Conversation\ConversationRepository;
use App\Domain\Bot\ValueObject\Message;

class ChatService
{
    private GptInterface $gpt;
    private ConversationRepository $conversationRepository;
    private ChatPromptService $chatPromptService;
    private WebSearchDomainService $webSearchDomainService;

    const RECENT_CONVERSATIONS_COUNT_FOR_GPT = 10;

    public function __construct(
        GptInterface $gpt,
        ConversationRepository $conversationRepository,
        ChatPromptService $chatPromptService,
        WebSearchDomainService $webSearchDomainService
    ) {
        $this->gpt = $gpt;
        $this->conversationRepository = $conversationRepository;
        $this->chatPromptService = $chatPromptService;
        $this->webSearchDomainService = $webSearchDomainService;
    }

    public function generateAnswer(Bot $bot, Message $message): string
    {
        $recentConversations = $this->conversationRepository->findByBotId(
            $bot->getId(),
            self::RECENT_CONVERSATIONS_COUNT_FOR_GPT
        );

        $webSearchResults = $this->webSearchDomainService->performWebSearchIfNeeded($message->getContent());

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
}
