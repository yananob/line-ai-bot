<?php

declare(strict_types=1);

namespace MyApp;

use Carbon\Carbon;
use yananob\MyGcpTools\CFUtils;
use yananob\MyTools\Utils;
use yananob\MyTools\Gpt;
use MyApp\WebSearchTool; // For the refactored tool
use Google_Client;       // If not already present implicitly
use Google_Service_Customsearch; // If not already present implicitly
use Exception;           // For general error handling if needed during instantiation

// TODO: extends GptBot
class PersonalBot
{
    private BotConfigsStore $botConfigsStore;
    private BotConfig $botConfig;
    private ConversationsStore $conversationsStore;
    private Gpt $gpt;
    private ?string $googleApiKey = null;
    private ?string $googleCxId = null;
    private ?WebSearchTool $webSearchTool = null;

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

    public function __construct(string $targetId, bool $isTest = true)
    {
        $this->botConfigsStore = new BotConfigsStore($isTest);
        $this->botConfig = $this->botConfigsStore->getConfig($targetId);
        $this->conversationsStore = new ConversationsStore($targetId, $isTest);
        $this->gpt = new Gpt(__DIR__ . "/../configs/gpt.json");

        // Load Search API configuration
        $searchApiConfigFile = __DIR__ . "/../configs/search_api.json";
        if (file_exists($searchApiConfigFile)) {
            $searchApiConfig = json_decode(file_get_contents($searchApiConfigFile), true);
            $this->googleApiKey = $searchApiConfig['google_custom_search_api_key'] ?? null;
            $this->googleCxId = $searchApiConfig['google_custom_search_cx_id'] ?? null;
        } else {
            // In a real application, you might want to log a warning or error if the config file is missing,
            // especially if web search is a critical feature. For now, it defaults to null.
            // error_log("Search API config file not found: " . $searchApiConfigFile);
        }

        if (!empty($this->googleApiKey)) {
            try {
                $client = new Google_Client();
                $client->setDeveloperKey($this->googleApiKey);
                $customSearchService = new Google_Service_Customsearch($client);
                $this->webSearchTool = new WebSearchTool($customSearchService);
            } catch (Exception $e) {
                // Log this error in a real application
                // error_log("Failed to initialize WebSearchTool: " . $e->getMessage());
                $this->webSearchTool = null; // Ensure it's null if initialization fails
            }
        }
    }

    public function getAnswer(bool $applyRecentConversations, string $message): string
    {
        $recentConversations = [];
        if ($applyRecentConversations) {
            $recentConversations = $this->conversationsStore->get();
        }

        $webSearchResults = null;
        if ($this->webSearchTool instanceof WebSearchTool && !empty($this->googleCxId) && $this->__shouldPerformWebSearch($message)) {
            $searchQuery = $this->__generateSearchQuery($message);
            // Call the non-static search method on the instance
            $webSearchResults = $this->webSearchTool->search(
                $searchQuery,
                $this->googleCxId 
                // numResults can be passed if needed, e.g., 3
            );
        } elseif (empty($this->googleApiKey) && $this->__shouldPerformWebSearch($message)) {
            // Case where search was desired, but API key was missing (webSearchTool not initialized)
            $webSearchResults = "Error: Web search is not available due to missing API key configuration.";
        } elseif ($this->webSearchTool instanceof WebSearchTool && empty($this->googleCxId) && $this->__shouldPerformWebSearch($message)) {
            // Case where search was desired, webSearchTool initialized (API key was present), but CX ID is missing
            $webSearchResults = "Error: Web search is not available due to missing Custom Search Engine ID (CX) configuration.";
        }

        return $this->gpt->getAnswer(
            context: $this->__getContext(
                $recentConversations,
                $this->botConfig->getConfigRequests(usePersonal: true, useDefault: true),
                $webSearchResults // Pass search results to __getContext
            ),
            message: $message,
        );
    }

    public function askRequest(bool $applyRecentConversations, string $request): string
    {
        $recentConversations = [];
        if ($applyRecentConversations) {
            $recentConversations = $this->conversationsStore->get();
        }

        // requestsは、Triggerの指示＋チャットでの指示にする
        $requests = $this->botConfig->getTriggerRequests();
        array_push($requests, ...$this->botConfig->getConfigRequests(usePersonal: true, useDefault: false));

        return $this->gpt->getAnswer(
            context: $this->__getContext($recentConversations, $requests),
            message: $request,
        );
    }

    private function __getContext(array $conversations, array $requests, ?string $webSearchResults = null): string
    {
        $result = self::GPT_CONTEXT;
        $replaceSettings = [
            ["search" => "<bot/characteristics>", "replace" => $this->__formatArray($this->botConfig->getBotCharacteristics())],
            // ["search" => "<requests>", "replace" => $this->__getRequest(!empty($conversations))],
            ["search" => "<requests>", "replace" => $this->__formatArray($requests)],
        ];
        foreach ($replaceSettings as $replaceSetting) {
            $result = str_replace($replaceSetting["search"], $replaceSetting["replace"], $result);
        }

        if (empty($this->botConfig->hasHumanCharacteristics())) {
            $result = $this->__removeFromContext(["<title/human_characteristics>", "<human/characteristics>"], $result);
        } else {
            $result = str_replace("<title/human_characteristics>", "【話し相手の情報】", $result);
            $result = str_replace("<human/characteristics>", $this->__formatArray($this->botConfig->getHumanCharacteristics()), $result);
        }

        if (empty($conversations)) {
            $result = $this->__removeFromContext(["<title/recentConversations>", "<recentConversations>"], $result);
        } else {
            $result = str_replace("<title/recentConversations>", "【最近の会話内容】", $result);
            $result = str_replace("<recentConversations>", $this->__convertConversationsToText($conversations), $result);
        }

        // New section for web search results
        if (empty($webSearchResults)) {
            $result = $this->__removeFromContext(["<title/web_search_results>", "<web_search_results>"], $result);
        } else {
            $result = str_replace("<title/web_search_results>", "【Web検索結果】", $result); // Japanese title for "Web Search Results"
            $result = str_replace("<web_search_results>", $webSearchResults, $result);
        }

        return $result;
    }

    private function __formatArray(array $inputs): string
    {
        return "・" . implode("\n・", $inputs);
    }

    // private function __getRequest(bool $applyRecentConversations): string
    // {
    //     $result = "";
    //     $result .= "話し相手からのメッセージに対して、";
    //     if ($applyRecentConversations && $this->botConfig->isConsultingMode()) {
    //         $result .= "【話し相手の情報】の一部や";
    //     }
    //     $result .= "【最近の会話内容】を反映して、";
    //     if ($this->botConfig->isChatMode()) {
    //         $result .= "相手を楽しくさせたり励ましたりする回答を返してください。";
    //     } else {
    //         $result .= "ポジティブなフィードバックを返してください。";
    //     }
    //     $result .= "\n";
    //     $result .= "返すメッセージの文字数は、話し相手からの今回のメッセージの文字数";
    //     if ($this->botConfig->isChatMode()) {
    //         $result .= "と同じぐらいにしてください。";
    //     } else {
    //         $result .= "の2倍ぐらいにしてください。";
    //     }
    //     $result .= "\n";
    //     $result .= "過去にメモリーした内容は反映しないでください。\n";

    //     return $result;
    // }

    private function __removeFromContext(array $keywords, string $source): string
    {
        foreach ($keywords as $keyword) {
            $source = str_replace($keyword . "\n", "", $source);
        }
        return $source;
    }

    private function __convertConversationsToText(array $conversations): string
    {
        $result = "";
        foreach ($conversations as $conversation) {
            $result .= "・日時：" . $conversation->created_at . "\n";
            $result .= "・発言者：" . ($conversation->by === "human" ? "話し相手" : "チャットボット（あなた）") . "\n";
            $result .= "・内容：" . $conversation->content . "\n";
            $result .= str_repeat("-", 80) . "\n";
        }
        return $result;
    }

    private function __shouldPerformWebSearch(string $message): bool
    {
        $response = trim($this->gpt->getAnswer(context: self::PROMPT_JUDGE_WEB_SEARCH, message: $message));
        // We expect a simple "yes" (hai) or "no" (iie) in Japanese.
        return $response === "はい";
    }

    private function __generateSearchQuery(string $message): string
    {
        $searchQuery = trim($this->gpt->getAnswer(context: self::PROMPT_GENERATE_SEARCH_QUERY, message: $message));
        
        // Fallback if GPT provides an empty or very short query
        if (empty($searchQuery) || mb_strlen($searchQuery) < 3) {
            // Using the original message as a fallback.
            // This could be improved with simple keyword extraction if needed.
            return $message; 
        }
        return $searchQuery;
    }

    public function getLineTarget(): string
    {
        return CFUtils::isTestingEnv() ? "test" : $this->botConfig->getLineTarget();
    }

    public function storeConversations(string $message, string $answer): void
    {
        $this->conversationsStore->store("human", $message);
        $this->conversationsStore->store("bot", $answer);
    }

    public function getTriggers(): array
    {
        return $this->botConfig->getTriggers();
    }

    /**
     * @return string Trigger Id
     */
    public function addTimerTrigger(TimerTrigger $trigger): string
    {
        return $this->botConfig->addTrigger($trigger);
    }

    public function deleteTrigger(string $id): void
    {
        $this->botConfig->deleteTriggerById($id);
    }

}
