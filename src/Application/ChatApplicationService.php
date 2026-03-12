<?php declare(strict_types=1);

namespace MyApp\Application;

use Exception;           // For general error handling
use OpenAI; // For OpenAI::client() factory
use OpenAI\Client as OpenAiClient; // For type-hinting if needed, and WebSearchTool constructor
use MyApp\Domain\Bot\Bot;
use MyApp\Domain\Bot\BotRepository;
use MyApp\Domain\Conversation\Conversation;
use MyApp\Domain\Conversation\ConversationRepository;
use MyApp\Domain\Bot\Trigger\Trigger;
use MyApp\Domain\Bot\Trigger\TimerTrigger;
use MyApp\Domain\Bot\Service\ChatPromptService;
use MyApp\Domain\Bot\Service\WebSearchInterface;
use yananob\MyGcpTools\CFUtils; // Keep for getLineTarget
use yananob\MyTools\Gpt;

// TODO: extends GptBot (This comment can be reviewed based on future plans)
class ChatApplicationService
{
    private BotRepository $botRepository;
    private ConversationRepository $conversationRepository;
    private ChatPromptService $chatPromptService;
    private Bot $bot;
    private Gpt $gpt;
    private ?WebSearchInterface $webSearchTool;

    const RECENT_CONVERSATIONS_COUNT_FOR_GPT = 10; // As per instructions

    const PROMPT_JUDGE_WEB_SEARCH = <<<EOM
あなたはユーザーからのメッセージを分析するアシスタントです。
ユーザーのメッセージに答えるためにWeb検索が必要かどうかを判断してください。
Web検索が必要な場合は「はい」、そうでない場合は「いいえ」とだけ答えてください。
EOM;

    public function __construct(
        Bot $bot,
        BotRepository $botRepository,
        ConversationRepository $conversationRepository,
        ChatPromptService $chatPromptService,
        Gpt $gpt,
        ?WebSearchInterface $webSearchTool = null
    ) {
        $this->bot = $bot;
        $this->botRepository = $botRepository;
        $this->conversationRepository = $conversationRepository;
        $this->chatPromptService = $chatPromptService;
        $this->gpt = $gpt;
        $this->webSearchTool = $webSearchTool;
    }

    public function getAnswer(bool $applyRecentConversations, string $message): string
    {
        $recentConversations = [];
        if ($applyRecentConversations) {
            $recentConversations = $this->conversationRepository->findByBotId(
                $this->bot->getId(),
                self::RECENT_CONVERSATIONS_COUNT_FOR_GPT
            );
        }

        // TODO: Google APIを使っていたときのように、いちど検索語を作ってから検索しているので、最適な処理じゃゃなさそう
        $webSearchResults = null;
        if ($this->__shouldPerformWebSearch($message)) {
            if ($this->webSearchTool instanceof WebSearchInterface) {
                $webSearchResults = $this->webSearchTool->search(
                    $message, // Use raw message as search query
                    5 // Number of results
                );
            } else {
                $webSearchResults = "Error: Web search tool is not configured properly or failed to initialize.";
            }
        }
        
        // Use bot's config requests (personal and default)
        $configRequests = $this->bot->getConfigRequests(usePersonal: true, useDefault: true);

        return $this->gpt->getAnswer(
            context: $this->chatPromptService->generateContext(
                $this->bot,
                $recentConversations, // Array of Conversation entities
                $configRequests,      // Bot's configured requests
                $webSearchResults
            ),
            message: $message,
            // options: ["reasoning_effort" => "minimal"],
        );
    }

    public function askRequest(bool $applyRecentConversations, string $requestMessage): string
    {
        $recentConversations = [];
        if ($applyRecentConversations) {
            $recentConversations = $this->conversationRepository->findByBotId(
                $this->bot->getId(),
                self::RECENT_CONVERSATIONS_COUNT_FOR_GPT
            );
        }
        $requests = $this->bot->getConfigRequests(usePersonal: true, useDefault: true);

        return $this->gpt->getAnswer(
            context: $this->chatPromptService->generateContext($this->bot, $recentConversations, $requests),
            message: $requestMessage,
        );
    }

    private function __shouldPerformWebSearch(string $message): bool
    {
        $response = trim($this->gpt->getAnswer(context: self::PROMPT_JUDGE_WEB_SEARCH, message: $message));
        return $response === "はい";
    }

    public function getLineTarget(): string
    {
        return CFUtils::isTestingEnv() ? "test" : $this->bot->getLineTarget();
    }

    public function storeConversations(string $message, string $answer): void
    {
        $humanConversation = new Conversation(
            $this->bot->getId(),
            "human",
            $message
        );
        $this->conversationRepository->save($humanConversation);

        $botConversation = new Conversation(
            $this->bot->getId(),
            "bot",
            $answer
        );
        $this->conversationRepository->save($botConversation);
    }

    /**
     * @return Trigger[]
     */
    public function getTriggers(): array
    {
        return $this->bot->getTriggers(); 
    }

    /**
     * @param TimerTrigger $trigger
     * @return string Trigger Id
     */
    public function addTimerTrigger(TimerTrigger $trigger): string
    {
        $newTriggerId = $this->bot->addTrigger($trigger);
        $this->botRepository->save($this->bot); 
        return $newTriggerId;
    }

    public function deleteTrigger(string $id): void
    {
        $this->bot->deleteTriggerById($id);
        $this->botRepository->save($this->bot); 
    }
}
