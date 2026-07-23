<?php

declare(strict_types=1);

namespace App\Application\CommandHandler;

use App\Domain\Bot\ValueObject\Command;
use App\Domain\Bot\Bot;
use App\Domain\Conversation\Conversation;
use App\Domain\Conversation\ConversationRepository;
use App\Domain\Conversation\ValueObject\Speaker;
use App\Domain\Bot\Service\ChatService;
use App\Application\BotResponse;
use App\Domain\Bot\ValueObject\Message;

class DefaultChatHandler implements CommandHandlerInterface
{
    private ChatService $chatService;
    private ConversationRepository $conversationRepository;

    public function __construct(
        ChatService $chatService,
        ConversationRepository $conversationRepository
    ) {
        $this->chatService = $chatService;
        $this->conversationRepository = $conversationRepository;
    }

    public function canHandle(Command $command): bool
    {
        return $command === Command::Other;
    }

    public function handle(Message $message, Bot $bot, Command $command): BotResponse
    {
        $answer = $this->chatService->generateAnswer($bot, $message);

        // Avoid storing system-triggered messages (e.g., timer executions) in conversation history.
        if (!$message->isSystem()) {
            $this->storeConversations($bot, $message->getContent(), $answer);
        }

        return new BotResponse($answer);
    }

    private function storeConversations(Bot $bot, string $messageContent, string $answer): void
    {
        $humanConversation = new Conversation($bot->getId(), Speaker::HUMAN, $messageContent);
        $this->conversationRepository->save($humanConversation);

        $botConversation = new Conversation($bot->getId(), Speaker::BOT, $answer);
        $this->conversationRepository->save($botConversation);
    }
}
