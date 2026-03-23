<?php

declare(strict_types=1);

namespace App\Application\CommandHandler;

use App\Domain\Bot\ValueObject\Command;
use App\Domain\Bot\Bot;
use App\Domain\Conversation\Conversation;
use App\Domain\Conversation\ConversationRepository;
use App\Domain\Bot\Service\ChatPromptService;
use App\Domain\Bot\Service\WebSearchInterface;
use App\Domain\Bot\Service\GptInterface;
use App\Application\BotResponse;

class DefaultChatHandler implements CommandHandlerInterface
{
    private GptInterface $gpt;
    private ConversationRepository $conversationRepository;
    private ChatPromptService $chatPromptService;
    private ?WebSearchInterface $webSearchTool;

    const RECENT_CONVERSATIONS_COUNT_FOR_GPT = 10;
    const PROMPT_JUDGE_WEB_SEARCH = <<<EOM
あなたはユーザーからのメッセージを分析するアシスタントです。
ユーザーのメッセージに答えるためにWeb検索が必要かどうかを判断してください。
Web検索が必要な場合は「はい」、そうでない場合は「いいえ」とだけ答えてください。
EOM;

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

    public function canHandle(Command $command): bool
    {
        return $command === Command::Other;
    }

    public function handle(string $message, Bot $bot, Command $command): BotResponse
    {
        $answer = $this->getAnswer($bot, $message);

        // Avoid storing system-triggered messages (e.g., timer executions) in conversation history.
        if (!str_starts_with($message, "【システム：タイマー実行】")) {
            $this->storeConversations($bot, $message, $answer);
        }

        return new BotResponse($answer);
    }

    private function getAnswer(Bot $bot, string $message): string
    {
        $recentConversations = $this->conversationRepository->findByBotId(
            $bot->getId(),
            self::RECENT_CONVERSATIONS_COUNT_FOR_GPT
        );

        $webSearchResults = null;
        if ($this->shouldPerformWebSearch($message)) {
            if ($this->webSearchTool instanceof WebSearchInterface) {
                $webSearchResults = $this->webSearchTool->search($message, 5);
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
            message: $message,
        );
    }

    private function shouldPerformWebSearch(string $message): bool
    {
        $response = trim($this->gpt->getAnswer(
            context: self::PROMPT_JUDGE_WEB_SEARCH,
            message: $message,
        ));
        return $response === "はい";
    }

    private function storeConversations(Bot $bot, string $message, string $answer): void
    {
        $humanConversation = new Conversation($bot->getId(), "human", $message);
        $this->conversationRepository->save($humanConversation);

        $botConversation = new Conversation($bot->getId(), "bot", $answer);
        $this->conversationRepository->save($botConversation);
    }
}
