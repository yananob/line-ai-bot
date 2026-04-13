<?php declare(strict_types=1);

namespace Tests\Domain\Bot\Service;

use PHPUnit\Framework\TestCase;
use App\Domain\Bot\Bot;
use App\Domain\Conversation\Conversation;
use App\Domain\Bot\Service\ChatPromptService;
use App\Domain\Bot\ValueObject\StringList;

class ChatPromptServiceTest extends TestCase
{
    private ChatPromptService $service;

    protected function setUp(): void
    {
        $this->service = new ChatPromptService();
    }

    public function test_generateContext_with_full_info(): void
    {
        $bot = new Bot("bot-123");
        $bot->setBotCharacteristics(["Characteristic 1", "Characteristic 2"]);
        $bot->setHumanCharacteristics(["Human Char 1"]);

        $conversation1 = new Conversation("bot-123", "human", "Hello");
        $conversation2 = new Conversation("bot-123", "bot", "Hi there!");
        $conversations = [$conversation1, $conversation2];

        $requests = new StringList(["Request 1"]);
        $webSearchResults = "Search Results Content";

        $context = $this->service->generateContext($bot, $conversations, $requests, $webSearchResults);

        $this->assertStringContainsString("【チャットボット（あなた）の情報】", $context);
        $this->assertStringContainsString("・Characteristic 1", $context);
        $this->assertStringContainsString("・Characteristic 2", $context);
        $this->assertStringContainsString("【話し相手の情報】", $context);
        $this->assertStringContainsString("・Human Char 1", $context);
        $this->assertStringContainsString("【最近の会話内容】", $context);
        $this->assertStringContainsString("Hello", $context);
        $this->assertStringContainsString("Hi there!", $context);
        $this->assertStringContainsString("【Web検索結果】", $context);
        $this->assertStringContainsString("Search Results Content", $context);
        $this->assertStringContainsString("【依頼事項の前提】", $context);
        $this->assertStringContainsString("・Request 1", $context);
    }

    public function test_generateContext_with_minimal_info(): void
    {
        $bot = new Bot("bot-456");
        $bot->setBotCharacteristics([]);
        $bot->setHumanCharacteristics([]);

        $conversations = [];
        $requests = new StringList([]);
        $webSearchResults = null;

        $context = $this->service->generateContext($bot, $conversations, $requests, $webSearchResults);

        $this->assertStringContainsString("【チャットボット（あなた）の情報】", $context);
        $this->assertStringNotContainsString("【話し相手の情報】", $context);
        $this->assertStringNotContainsString("【最近の会話内容】", $context);
        $this->assertStringNotContainsString("【Web検索結果】", $context);
        $this->assertStringContainsString("【依頼事項の前提】", $context);
    }

    public function test_generateContext_excludes_empty_sections(): void
    {
        $bot = new Bot("bot-789");
        $bot->setBotCharacteristics([]);
        $bot->setHumanCharacteristics([]);

        $conversations = [];
        $requests = new StringList([]);
        $webSearchResults = null;

        $context = $this->service->generateContext($bot, $conversations, $requests, $webSearchResults);

        // Sections that should be excluded when empty
        $this->assertStringNotContainsString("【話し相手の情報】", $context);
        $this->assertStringNotContainsString("【最近の会話内容】", $context);
        $this->assertStringNotContainsString("【Web検索結果】", $context);

        // bot characteristics and requests are formatted by StringList, which might return empty string or specific format
        // ChatPromptService logic for characteristics and requests currently doesn't remove the section title
        $this->assertStringContainsString("【チャットボット（あなた）の情報】", $context);
        $this->assertStringContainsString("【依頼事項の前提】", $context);
    }

    public function test_generateContext_includes_web_search_results_when_provided(): void
    {
        $bot = new Bot("bot-abc");
        $requests = new StringList([]);
        $webSearchResults = "This is a search result.";

        $context = $this->service->generateContext($bot, [], $requests, $webSearchResults);

        $this->assertStringContainsString("【Web検索結果】", $context);
        $this->assertStringContainsString("This is a search result.", $context);
    }
}
