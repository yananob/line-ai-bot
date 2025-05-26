<?php

declare(strict_types=1);

use Carbon\Carbon;
use MyApp\LogicBot;
use yananob\MyTools\Test;
use MyApp\PersonalBot;
use yananob\MyTools\Gpt; // For mocking
use MyApp\WebSearchTool; // For mocking the WebSearchTool instance
// PHPUnit\Framework\TestCase is typically available globally or via autoloader

final class PersonalBotTest extends PHPUnit\Framework\TestCase
{
    private PersonalBot $bot;
    private PersonalBot $bot_default;

    protected function setUp(): void
    {
        // $this->bot_chat = new PersonalBot("TARGET_ID_TEST_CHAT");
        // $this->bot_consulting = new PersonalBot("TARGET_ID_TEST_CONSULTING");
        $this->bot = new PersonalBot("TARGET_ID_AUTOTEST");
        $this->bot_default = new PersonalBot("TARGET_ID_NOT_EXISTS");
    }

    public function testGetAnswerWithoutRecentConversation()
    {
        $this->assertNotEmpty($this->bot->getAnswer(
            false,
            "今年のクリスマスは何月何日でしょうか？\n昨年のクリスマスとは違うのでしょうか？"
        ));
    }

    public function testGetAnswerWithRecentConversation()
    {
        $this->assertNotEmpty($this->bot->getAnswer(
            true,
            "今年のクリスマスは何月何日でしょうか？\n昨年のクリスマスとは違うのでしょうか？"
        ));
    }

    public function testAskRequestWithoutRecentConversation()
    {
        $this->assertNotEmpty($this->bot->askRequest(
            false,
            "今年のクリスマスメッセージを送って"
        ));
    }

    public function testGetContext_WithOutTargetConfiguration()
    {
        $this->assertStringNotContainsString(
            "【話し相手の情報】\n",
            Test::invokePrivateMethod(
                $this->bot_default,
                "__getContext",
                [],
                ["話し相手からのメッセージに対して、【最近の会話内容】を反映して、回答を返してください。"]
            )
        );
    }
    public function testGetContext_WithTargetConfiguration()
    {
        $this->assertStringContainsString(
            "【話し相手の情報】\n",
            Test::invokePrivateMethod(
                $this->bot,
                "__getContext",
                [],
                ["話し相手からのメッセージに対して、【最近の会話内容】を反映して、回答を返してください。"]
            )
        );
    }

    public function testGetContext_WithoutRecentConversation()
    {
        $this->assertStringNotContainsString(
            "【最近の会話内容】\n",
            Test::invokePrivateMethod(
                $this->bot_default,
                "__getContext",
                [],
                ["話し相手からのメッセージに対して、【最近の会話内容】を反映して、回答を返してください。"]
            )
        );
    }
    public function testGetContext_WithRecentConversation()
    {
        $recentConversations = [];
        $obj = new stdClass();
        $obj->by = "human";
        $obj->content = "今日は旅行に行きました";
        $obj->created_at = new Carbon("today");
        $recentConversations[] = $obj;

        $this->assertStringContainsString(
            "【最近の会話内容】\n",
            Test::invokePrivateMethod(
                $this->bot,
                "__getContext",
                $recentConversations,
                ["話し相手からのメッセージに対して、【最近の会話内容】を反映して、回答を返してください。"]
            )
        );
    }

    public function testGetLineTarget_WithTargetConfiguration()
    {
        $this->assertSame("test", $this->bot->getLineTarget());
    }

    public function testGetLineTarget_WithOutTargetConfiguration()
    {
        $this->assertSame("test", $this->bot_default->getLineTarget());
    }

    public function testAddTimerTrigger(): void
    {
        $logicBot = new LogicBot();

        $trigger = $logicBot->generateOneTimeTrigger("1時間後に「できたよ」と送って");
        $id = $this->bot->addTimerTrigger($trigger);
        $this->bot->deleteTrigger($id);

        $trigger = $logicBot->generateOneTimeTrigger("11時半に「ご飯だよ」と送って");
        $id = $this->bot->addTimerTrigger($trigger);
        $this->bot->deleteTrigger($id);

        $trigger = $logicBot->generateDailyTrigger("毎日7時半に天気予報を送って");
        $id = $this->bot->addTimerTrigger($trigger);
        $this->bot->deleteTrigger($id);

        $this->assertTrue(true);
    }

    // Helper method for setting private properties
    protected function setPrivateProperty($object, string $propertyName, $value): void
    {
        $reflection = new \ReflectionClass($object);
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);
        $property->setValue($object, $value);
    }

    // Tests for __shouldPerformWebSearch
    public function testShouldPerformWebSearchReturnsTrueWhenGptSaysYes(): void
    {
        $gptMock = $this->createMock(Gpt::class);
        $gptMock->method('getAnswer')
                ->with(PersonalBot::PROMPT_JUDGE_WEB_SEARCH, 'some message')
                ->willReturn('はい'); // "Yes" in Japanese

        $bot = new PersonalBot("TARGET_ID_AUTOTEST_SEARCH_YES");
        $this->setPrivateProperty($bot, 'gpt', $gptMock);

        $result = Test::invokePrivateMethod($bot, '__shouldPerformWebSearch', 'some message');
        $this->assertTrue($result);
    }

    public function testShouldPerformWebSearchReturnsFalseWhenGptSaysNo(): void
    {
        $gptMock = $this->createMock(Gpt::class);
        $gptMock->method('getAnswer')
                ->with(PersonalBot::PROMPT_JUDGE_WEB_SEARCH, 'another message')
                ->willReturn('いいえ'); // "No" in Japanese

        $bot = new PersonalBot("TARGET_ID_AUTOTEST_SEARCH_NO");
        $this->setPrivateProperty($bot, 'gpt', $gptMock);

        $result = Test::invokePrivateMethod($bot, '__shouldPerformWebSearch', 'another message');
        $this->assertFalse($result);
    }

    public function testShouldPerformWebSearchReturnsFalseOnUnexpectedGptResponse(): void
    {
        $gptMock = $this->createMock(Gpt::class);
        $gptMock->method('getAnswer')
                ->with(PersonalBot::PROMPT_JUDGE_WEB_SEARCH, 'different message')
                ->willReturn('わかりません'); // "I don't know"

        $bot = new PersonalBot("TARGET_ID_AUTOTEST_SEARCH_UNEXPECTED");
        $this->setPrivateProperty($bot, 'gpt', $gptMock);

        $result = Test::invokePrivateMethod($bot, '__shouldPerformWebSearch', 'different message');
        $this->assertFalse($result);
    }

    // Tests for __getContext (related to web search results)
    public function testGetContextIncludesWebSearchResultsWhenProvided(): void
    {
        $bot = $this->bot; // Use existing bot instance from setUp
        $conversations = [];
        $requests = ["some request"];
        $searchResults = "Found web information: A, B, C.";

        $context = Test::invokePrivateMethod(
            $bot,
            '__getContext',
            $conversations, // arg1 for __getContext
            $requests,      // arg2 for __getContext
            $searchResults  // arg3 for __getContext
        );

        $this->assertStringContainsString("【Web検索結果】", $context);
        $this->assertStringContainsString($searchResults, $context);
    }

    public function testGetContextExcludesWebSearchResultsWhenNull(): void
    {
        $bot = $this->bot;
        $conversations = [];
        $requests = ["some request"];

        $context = Test::invokePrivateMethod(
            $bot,
            '__getContext',
            $conversations,
            $requests,
            null // webSearchResults argument
        );

        $this->assertStringNotContainsString("【Web検索結果】", $context);
        $this->assertStringNotContainsString("<web_search_results>", $context); // Placeholder should be removed
    }

    public function testGetAnswerPerformsSearchWhenIndicatedAndIncludesResultsInContext(): void
    {
        $userMessage = "What is the weather like?";
        $dummySearchQuery = "weather query"; // Expected from PROMPT_GENERATE_SEARCH_QUERY
        // This is the expected result when WebSearchTool::search is called with null API key/CX
        $expectedSearchResultsInContext = "Error: API key or CX ID is not configured for web search.";
        $expectedFinalAnswer = "The weather is fine based on web search.";

        $gptMock = $this->createMock(Gpt::class);

        // Expectation 1: __shouldPerformWebSearch's GPT call
        $gptMock->expects($this->at(0))
                ->method('getAnswer')
                ->with(PersonalBot::PROMPT_JUDGE_WEB_SEARCH, $userMessage)
                ->willReturn('はい'); // Yes, search

        // Expectation 2: __generateSearchQuery's GPT call
        $gptMock->expects($this->at(1))
                ->method('getAnswer')
                ->with(PersonalBot::PROMPT_GENERATE_SEARCH_QUERY, $userMessage)
                ->willReturn($dummySearchQuery);

        // Expectation 3: Final answer generation GPT call
        $gptMock->expects($this->at(2))
                ->method('getAnswer')
                ->with(
                    $this->callback(function ($context) use ($expectedSearchResultsInContext) {
                        $this->assertStringContainsString("【Web検索結果】", $context);
                        $this->assertStringContainsString($expectedSearchResultsInContext, $context);
                        return true; // Context is good
                    }),
                    $userMessage
                )
                ->willReturn($expectedFinalAnswer);

        $bot = new PersonalBot("TARGET_ID_GETANSWER_SEARCH"); // This target ID should not have a search_api.json
        $this->setPrivateProperty($bot, 'gpt', $gptMock);
        // PersonalBot's constructor ensures API keys are null for this test ID
        // as configs/search_api.json for TARGET_ID_GETANSWER_SEARCH won't exist.

        $actualAnswer = $bot->getAnswer(true, $userMessage);
        $this->assertSame($expectedFinalAnswer, $actualAnswer);
    }

    public function testGetAnswerDoesNotSearchWhenNotIndicatedAndExcludesResultsFromContext(): void
    {
        $userMessage = "Hello there!";
        $expectedFinalAnswer = "Hello to you too!";

        $gptMock = $this->createMock(Gpt::class);

        // Expectation for __shouldPerformWebSearch's GPT call
        $gptMock->expects($this->at(0)) // First call to gpt->getAnswer
                ->method('getAnswer')
                ->with(PersonalBot::PROMPT_JUDGE_WEB_SEARCH, $userMessage)
                ->willReturn('いいえ'); // No, do not search

        // Expectation for the final answer generation GPT call
        $gptMock->expects($this->at(1)) // Second call to gpt->getAnswer
                ->method('getAnswer')
                ->with(
                    $this->callback(function ($context) {
                        $this->assertStringNotContainsString("【Web検索結果】", $context);
                        return true; // Context is good
                    }),
                    $userMessage
                )
                ->willReturn($expectedFinalAnswer);
        
        $bot = new PersonalBot("TARGET_ID_GETANSWER_NOSEARCH"); // Using a distinct target ID
        $this->setPrivateProperty($bot, 'gpt', $gptMock);

        $actualAnswer = $bot->getAnswer(true, $userMessage); // applyRecentConversations = true
        $this->assertSame($expectedFinalAnswer, $actualAnswer);
    }

    public function testGenerateSearchQueryUsesGptResponse(): void
    {
        $userMessage = "What is the weather like tomorrow in Tokyo?";
        $expectedQuery = "weather tomorrow Tokyo";

        $gptMock = $this->createMock(Gpt::class);
        $gptMock->method('getAnswer')
                ->with(PersonalBot::PROMPT_GENERATE_SEARCH_QUERY, $userMessage)
                ->willReturn($expectedQuery);

        $bot = new PersonalBot("TARGET_ID_GENERATE_QUERY"); // Use a distinct ID if needed
        $this->setPrivateProperty($bot, 'gpt', $gptMock);

        $actualQuery = Test::invokePrivateMethod($bot, '__generateSearchQuery', $userMessage);
        $this->assertSame($expectedQuery, $actualQuery);
    }

    public function testGenerateSearchQueryFallbackToOriginalMessageIfGptResponseIsEmpty(): void
    {
        $userMessage = "A complex question with no easy keywords.";
        
        $gptMock = $this->createMock(Gpt::class);
        $gptMock->method('getAnswer')
                ->with(PersonalBot::PROMPT_GENERATE_SEARCH_QUERY, $userMessage)
                ->willReturn(''); // Empty response from GPT

        $bot = new PersonalBot("TARGET_ID_GENERATE_QUERY_EMPTY");
        $this->setPrivateProperty($bot, 'gpt', $gptMock);

        $actualQuery = Test::invokePrivateMethod($bot, '__generateSearchQuery', $userMessage);
        $this->assertSame($userMessage, $actualQuery); // Should fallback to original
    }

    public function testGenerateSearchQueryFallbackToOriginalMessageIfGptResponseIsTooShort(): void
    {
        $userMessage = "Another question.";
        
        $gptMock = $this->createMock(Gpt::class);
        $gptMock->method('getAnswer')
                ->with(PersonalBot::PROMPT_GENERATE_SEARCH_QUERY, $userMessage)
                ->willReturn('a'); // Too short response

        $bot = new PersonalBot("TARGET_ID_GENERATE_QUERY_SHORT");
        $this->setPrivateProperty($bot, 'gpt', $gptMock);

        $actualQuery = Test::invokePrivateMethod($bot, '__generateSearchQuery', $userMessage);
        $this->assertSame($userMessage, $actualQuery); // Should fallback to original
    }

    public function testGetAnswerUsesWebSearchToolInstanceWhenConfigured(): void
    {
        $userMessage = "Tell me about cats.";
        $dummySearchQuery = "cats information";
        $mockedSearchResults = "Web Search Results:
- Title: Cats are great. Snippet: Yes they are.";
        $expectedFinalAnswer = "Based on my research, cats are indeed great.";

        // Mock for Gpt (for decision, query gen, and final answer)
        $gptMock = $this->createMock(Gpt::class);
        $gptMock->expects($this->at(0)) // PROMPT_JUDGE_WEB_SEARCH
                ->method('getAnswer')
                ->with(PersonalBot::PROMPT_JUDGE_WEB_SEARCH, $userMessage)
                ->willReturn('はい');
        $gptMock->expects($this->at(1)) // PROMPT_GENERATE_SEARCH_QUERY
                ->method('getAnswer')
                ->with(PersonalBot::PROMPT_GENERATE_SEARCH_QUERY, $userMessage)
                ->willReturn($dummySearchQuery);
        $gptMock->expects($this->at(2)) // Final Answer
                ->method('getAnswer')
                ->with(
                    $this->callback(function ($context) use ($mockedSearchResults) {
                        $this->assertStringContainsString("【Web検索結果】", $context);
                        $this->assertStringContainsString($mockedSearchResults, $context);
                        return true;
                    }),
                    $userMessage
                )
                ->willReturn($expectedFinalAnswer);

        // Mock for WebSearchTool
        $webSearchToolMock = $this->createMock(WebSearchTool::class);
        $webSearchToolMock->expects($this->once())
            ->method('search')
            ->with($dummySearchQuery, "DUMMY_CX_ID") // Assert query and CX ID are passed
            ->willReturn($mockedSearchResults);

        // Create PersonalBot instance
        // For this test, we need $this->googleApiKey and $this->googleCxId to be set,
        // and $this->webSearchTool to be our mock.
        $bot = new PersonalBot("TARGET_ID_WEBSEARCH_CONFIGURED"); 
        $this->setPrivateProperty($bot, 'gpt', $gptMock);
        
        // Manually set API key, CX ID (as if loaded from a file) and inject the mock WebSearchTool
        $this->setPrivateProperty($bot, 'googleApiKey', "DUMMY_API_KEY");
        $this->setPrivateProperty($bot, 'googleCxId', "DUMMY_CX_ID");
        $this->setPrivateProperty($bot, 'webSearchTool', $webSearchToolMock); 
        // Note: By setting webSearchTool directly, we bypass PersonalBot's own instantiation
        // of WebSearchTool and Google_Client/Google_Service_Customsearch. This is fine for testing
        // PersonalBot's logic that *uses* an already instantiated WebSearchTool.

        $actualAnswer = $bot->getAnswer(true, $userMessage);
        $this->assertSame($expectedFinalAnswer, $actualAnswer);
    }
}
