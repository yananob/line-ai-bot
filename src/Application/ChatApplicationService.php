<?php declare(strict_types=1);

namespace MyApp\Application;

use MyApp\Domain\Bot\Bot;
use MyApp\Domain\Bot\BotRepository;
use MyApp\Domain\Conversation\Conversation;
use MyApp\Domain\Conversation\ConversationRepository;
use MyApp\Domain\Bot\Trigger\TimerTrigger;
use Carbon\Carbon; // Keep if used by other parts or for formatting
use yananob\MyGcpTools\CFUtils; // Keep for getLineTarget
use yananob\MyTools\Utils;    // Keep for __convertConversationsToText (original) or other formatting
use yananob\MyTools\Gpt;
use MyApp\WebSearchTool; // Assuming WebSearchTool is correctly located and used
use Google_Client;       // Keep if WebSearchTool or Gpt uses it
use Google\Service\CustomSearchAPI; // Keep if WebSearchTool uses it
use Exception;           // For general error handling
use MyApp\Domain\Bot\Trigger\Trigger;

// TODO: extends GptBot (This comment can be reviewed based on future plans)
class ChatApplicationService
{
    private string $targetId;
    private BotRepository $botRepository;
    private ConversationRepository $conversationRepository;
    private Bot $bot;
    private Gpt $gpt;
    private ?string $googleApiKey = null; // Retained for WebSearchTool initialization
    private ?string $googleCxId = null;   // Retained for WebSearchTool usage
    private ?WebSearchTool $webSearchTool = null;
    // private bool $isTest;

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

    const PROMPT_GENERATE_SEARCH_QUERY = <<<EOM
ユーザーのメッセージ内容から、Web検索エンジンで検索するための最も効果的な検索クエリを生成してください。
検索クエリは簡潔で、主要なキーワードを含むべきです。元のメッセージの意図を保持するようにしてください。
生成された検索クエリのみを返してください。
EOM;

    public function __construct(
        string $targetId,
        BotRepository $botRepository,
        ConversationRepository $conversationRepository,
        // bool $isTest = true
    ) {
        $this->targetId = $targetId;
        // $this->isTest = $isTest;
        $this->botRepository = $botRepository;
        $this->conversationRepository = $conversationRepository;

        $bot = $this->botRepository->findById($this->targetId);
        if ($bot === null) {
            // Attempt to load default if specific bot not found, or handle as error
            // For now, strict: if specific bot ID is given and not found, it's an error.
            throw new \RuntimeException("Bot with ID '{$this->targetId}' not found.");
        }
        $this->bot = $bot;

        // Path to gpt.json needs to be relative to this file's new location
        $this->gpt = new Gpt(__DIR__ . "/../../configs/gpt.json");

        // Load Search API configuration (path adjusted)
        $searchApiConfigFile = __DIR__ . "/../../configs/search_api.json";
        if (file_exists($searchApiConfigFile)) {
            $searchApiConfig = json_decode(file_get_contents($searchApiConfigFile), true);
            $this->googleApiKey = $searchApiConfig['google_custom_search_api_key'] ?? null;
            $this->googleCxId = $searchApiConfig['google_custom_search_cx_id'] ?? null;
        }

        if (!empty($this->googleApiKey)) {
            try {
                $client = new Google_Client();
                $client->setDeveloperKey($this->googleApiKey);
                $customSearchService = new CustomSearchAPI($client);
                $this->webSearchTool = new WebSearchTool($customSearchService);
            } catch (Exception $e) {
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

        $webSearchResults = null;
        if ($this->webSearchTool instanceof WebSearchTool && !empty($this->googleCxId) && $this->__shouldPerformWebSearch($message)) {
            $searchQuery = $this->__generateSearchQuery($message);
            $webSearchResults = $this->webSearchTool->search(
                $searchQuery,
                $this->googleCxId,
                5 // Number of results
            );
        } elseif (empty($this->googleApiKey) && $this->__shouldPerformWebSearch($message)) {
            $webSearchResults = "Error: Web search is not available due to missing API key configuration.";
        } elseif ($this->webSearchTool instanceof WebSearchTool && empty($this->googleCxId) && $this->__shouldPerformWebSearch($message)) {
            $webSearchResults = "Error: Web search is not available due to missing Custom Search Engine ID (CX) configuration.";
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

        // Fetching requests from the bot. This part needs clarification on "TriggerRequests"
        // For now, using bot's general config requests, similar to getAnswer.
        // If TriggerRequests are distinct and needed, Bot entity might need a specific method.
        $requests = $this->bot->getConfigRequests(usePersonal: true, useDefault: true);
        // The original code had:
        // $requests = $this->botConfig->getTriggerRequests();
        // array_push($requests, ...$this->botConfig->getConfigRequests(usePersonal: true, useDefault: false));
        // This implies trigger-specific requests were separate. If that's still the case,
        // that logic needs to be adapted to the Bot entity. For now, we use all config requests.

        return $this->gpt->getAnswer(
            context: $this->__getContext($recentConversations, $requests),
            message: $requestMessage, // Original used $request, assume it's $requestMessage
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
            ["search" => "<requests>", "replace" => $this->__formatArray($requests)], // $requests is now passed in
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
            // $conversations is now an array of Conversation entities
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
            // Also remove the keyword itself if it's the last line or followed by nothing in the template section
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
        foreach ($conversations as $conversation) { // $conversation is now a Conversation entity
            $result .= "・日時：" . $conversation->getCreatedAt()->format('Y-m-d H:i:s') . "\n"; // Use getter and format
            $speakerDisplay = ($conversation->getSpeaker() === "human") ? "話し相手" : "チャットボット（あなた）";
            $result .= "・発言者：" . $speakerDisplay . "\n";
            $result .= "・内容：" . $conversation->getContent() . "\n"; // Use getter
            $result .= str_repeat("-", 80) . "\n";
        }
        return $result;
    }

    private function __shouldPerformWebSearch(string $message): bool
    {
        $response = trim($this->gpt->getAnswer(context: self::PROMPT_JUDGE_WEB_SEARCH, message: $message));
        return $response === "はい";
    }

    private function __generateSearchQuery(string $message): string
    {
        $searchQuery = trim($this->gpt->getAnswer(context: self::PROMPT_GENERATE_SEARCH_QUERY, message: $message));
        if (empty($searchQuery) || mb_strlen($searchQuery) < 3) {
            return $message;
        }
        return $searchQuery;
    }

    public function getLineTarget(): string
    {
        // CFUtils might need to be checked if it's available globally or needs specific setup
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
        return $this->bot->getTriggers(); // Returns array of Trigger objects
    }

    /**
     * @param TimerTrigger $trigger
     * @return string Trigger Id
     */
    public function addTimerTrigger(TimerTrigger $trigger): string
    {
        $newTriggerId = $this->bot->addTrigger($trigger);
        $this->botRepository->save($this->bot); // Persist Bot entity changes
        return $newTriggerId;
    }

    public function deleteTrigger(string $id): void
    {
        $this->bot->deleteTriggerById($id);
        $this->botRepository->save($this->bot); // Persist Bot entity changes
    }
}
