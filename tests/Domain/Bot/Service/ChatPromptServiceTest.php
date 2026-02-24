<?php declare(strict_types=1);

namespace MyApp\Tests\Domain\Bot\Service;

use PHPUnit\Framework\TestCase;
use MyApp\Domain\Bot\Bot;
use MyApp\Domain\Conversation\Conversation;
use MyApp\Domain\Bot\Service\ChatPromptService;

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

        $requests = ["Request 1"];
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
        $requests = [];
        $webSearchResults = null;

        $context = $this->service->generateContext($bot, $conversations, $requests, $webSearchResults);

        $this->assertStringContainsString("【チャットボット（あなた）の情報】", $context);
        $this->assertStringNotContainsString("【話し相手の情報】", $context);
        $this->assertStringNotContainsString("【最近の会話内容】", $context);
        $this->assertStringNotContainsString("【Web検索結果】", $context);
        $this->assertStringContainsString("【依頼事項の前提】", $context);
    }
}
