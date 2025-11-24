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
use MyApp\WebSearchTool;
use yananob\MyGcpTools\CFUtils; // Keep for getLineTarget
use yananob\MyTools\Gpt;

// TODO: extends GptBot (This comment can be reviewed based on future plans)
class ChatApplicationService
{
    private string $targetId;
    private BotRepository $botRepository;
    private ConversationRepository $conversationRepository;
    private Bot $bot;
    private Gpt $gpt;
    private ?string $openaiApiKey = null;
    private ?string $openaiSearchModel = null;
    private ?WebSearchTool $webSearchTool = null;

    const RECENT_CONVERSATIONS_COUNT_FOR_GPT = 10; // As per instructions

    const GPT_CONTEXT = <<<EOM
【チャットボット（あなた）の情報】
<bot/characteristics>

<title/human_characteristics>
<human/characteristics>

<title/recentConversations>
<recentConversations>

<title/web_search_results>
<web_search_results>

【依頼事項の前提】
<requests>
EOM;

    const PROMPT_JUDGE_WEB_SEARCH = <<<EOM
あなたはユーザーからのメッセージを分析するアシスタントです。
ユーザーのメッセージに答えるためにWeb検索が必要かどうかを判断してください。
Web検索が必要な場合は「はい」、そうでない場合は「いいえ」とだけ答えてください。
EOM;

    public function __construct(
        string $targetId,
        BotRepository $botRepository,
        ConversationRepository $conversationRepository
    ) {
        $this->targetId = $targetId;
        $this->botRepository = $botRepository;
        $this->conversationRepository = $conversationRepository;

        $this->bot = $this->botRepository->findById($this->targetId);
        if ($this->bot === null) {
            // 指定のbotの設定がないときは、defaultの設定で動作する
            $this->bot = $this->botRepository->findDefault();
            if ($this->bot === null) {
                throw new \RuntimeException("Bot with ID '{$this->targetId}' not found.");
            }
        }

        $this->gpt = new Gpt(getenv("OPENAI_KEY_LINE_AI_BOT"), "gpt-5.1");

        // Load Search API configuration (path adjusted)
        $this->openaiApiKey = getenv("OPENAI_KEY_LINE_AI_BOT");
        $this->openaiSearchModel = "gpt-4o-mini";

        if (!empty($this->openaiApiKey) && !empty($this->openaiSearchModel)) {
            try {
                $openaiClient = OpenAI::client($this->openaiApiKey); // Uses the factory method
                $this->webSearchTool = new WebSearchTool($openaiClient, $this->openaiSearchModel);
            } catch (Exception $e) {
                // Log error appropriately in a real application
                error_log("Failed to initialize OpenAI client or WebSearchTool: " . $e->getMessage());
                $this->webSearchTool = null;
            }
        }
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
        if ($this->webSearchTool instanceof WebSearchTool && $this->__shouldPerformWebSearch($message)) {
            $webSearchResults = $this->webSearchTool->search(
                $message, // Use raw message as search query
                5 // Number of results
            );
        } elseif (empty($this->openaiApiKey) && $this->__shouldPerformWebSearch($message)) { // Check if API key is missing
            $webSearchResults = "Error: Web search is not available due to missing OpenAI API key configuration.";
        } elseif ($this->webSearchTool === null && $this->__shouldPerformWebSearch($message)) { // General check if tool failed to initialize
             $webSearchResults = "Error: Web search tool is not configured properly or failed to initialize.";
        }
        
        // Use bot's config requests (personal and default)
        $configRequests = $this->bot->getConfigRequests(usePersonal: true, useDefault: true);

        return $this->gpt->getAnswer(
            context: $this->__getContext(
                $recentConversations, // Array of Conversation entities
                $configRequests,      // Bot's configured requests
                $webSearchResults
            ),
            message: $message,
            options: ["reasoning_effort" => "minimal"],
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
            context: $this->__getContext($recentConversations, $requests),
            message: $requestMessage,
        );
    }

    /**
     * @param Conversation[] $conversations Array of Conversation entities
     * @param array $requests Array of request strings
     * @param string|null $webSearchResults
     * @return string
     */
    private function __getContext(array $conversations, array $requests, ?string $webSearchResults = null): string
    {
        $result = self::GPT_CONTEXT;
        $replaceSettings = [
            ["search" => "<bot/characteristics>", "replace" => $this->__formatArray($this->bot->getBotCharacteristics())],
            ["search" => "<requests>", "replace" => $this->__formatArray($requests)],
        ];
        foreach ($replaceSettings as $replaceSetting) {
            $result = str_replace($replaceSetting["search"], $replaceSetting["replace"], $result);
        }

        if (empty($this->bot->hasHumanCharacteristics())) {
            $result = $this->__removeFromContext(["<title/human_characteristics>", "<human/characteristics>"], $result);
        } else {
            $result = str_replace("<title/human_characteristics>", "【話し相手の情報】", $result);
            $result = str_replace("<human/characteristics>", $this->__formatArray($this->bot->getHumanCharacteristics()), $result);
        }

        if (empty($conversations)) {
            $result = $this->__removeFromContext(["<title/recentConversations>", "<recentConversations>"], $result);
        } else {
            $result = str_replace("<title/recentConversations>", "【最近の会話内容】", $result);
            $result = str_replace("<recentConversations>", $this->__convertConversationsToText($conversations), $result);
        }

        if (empty($webSearchResults)) {
            $result = $this->__removeFromContext(["<title/web_search_results>", "<web_search_results>"], $result);
        } else {
            $result = str_replace("<title/web_search_results>", "【Web検索結果】", $result);
            $result = str_replace("<web_search_results>", $webSearchResults, $result);
        }

        return $result;
    }

    private function __formatArray(array $inputs): string
    {
        if (empty($inputs)) return "";
        return "・" . implode("\n・", $inputs);
    }

    private function __removeFromContext(array $keywords, string $source): string
    {
        foreach ($keywords as $keyword) {
            $source = str_replace($keyword . "\n", "", $source);
            $source = str_replace($keyword, "", $source); 
        }
        return $source;
    }

    /**
     * @param Conversation[] $conversations
     */
    private function __convertConversationsToText(array $conversations): string
    {
        $result = "";
        foreach ($conversations as $conversation) { 
            $result .= "・日時：" . $conversation->getCreatedAt()->format('Y-m-d H:i:s') . "\n"; 
            $speakerDisplay = ($conversation->getSpeaker() === "human") ? "話し相手" : "チャットボット（あなた）";
            $result .= "・発言者：" . $speakerDisplay . "\n";
            $result .= "・内容：" . $conversation->getContent() . "\n"; 
            $result .= str_repeat("-", 80) . "\n";
        }
        return $result;
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
