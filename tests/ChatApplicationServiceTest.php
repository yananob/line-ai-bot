<?php

declare(strict_types=1);

use Carbon\Carbon;
use yananob\MyTools\Test;
use yananob\MyTools\Gpt; // For mocking
use MyApp\WebSearchTool; // For mocking the WebSearchTool instance

use MyApp\Application\ChatApplicationService;
use MyApp\Domain\Bot\BotRepository;
use MyApp\Domain\Conversation\ConversationRepository;
use MyApp\Domain\Bot\Bot;
use MyApp\Domain\Bot\Trigger\TimerTrigger;
use MyApp\Domain\Bot\Service\CommandAndTriggerService;
use MyApp\Domain\Conversation\Conversation; // For mocking conversation data

// PHPUnit\Framework\TestCase is typically available globally or via autoloader

final class ChatApplicationServiceTest extends PHPUnit\Framework\TestCase
{
    private ChatApplicationService $chatService; // Renamed from $bot
    // private ChatApplicationService $chatServiceWithNonExistentBot; // This concept needs re-evaluation

    private $botRepositoryMock;
    private $conversationRepositoryMock;
    private $gptMock;
    private $webSearchToolMock;
    private $commandAndTriggerServiceMock; // Not used directly by ChatApplicationService in current design

    const TARGET_ID_AUTOTEST = "TARGET_ID_AUTOTEST";
    const TARGET_ID_FOR_DEFAULT_BEHAVIOR = "TARGET_ID_FOR_DEFAULT_BEHAVIOR"; // A bot that will have minimal/default config
    const TARGET_ID_THAT_THROWS_EXCEPTION = "TARGET_ID_THAT_THROWS_EXCEPTION"; // For testing constructor failure

    protected function setUp(): void
    {
        $this->botRepositoryMock = $this->createMock(BotRepository::class);
        $this->conversationRepositoryMock = $this->createMock(ConversationRepository::class);
        $this->gptMock = $this->createMock(Gpt::class);
        $this->webSearchToolMock = $this->createMock(WebSearchTool::class);
        // $this->commandAndTriggerServiceMock = $this->createMock(CommandAndTriggerService::class); // Not directly injected into ChatApplicationService

        // --- Bot Mock for TARGET_ID_AUTOTEST (fully featured bot) ---
        $mockBotAutotest = $this->createMock(Bot::class);
        $mockBotAutotest->method('getId')->willReturn(self::TARGET_ID_AUTOTEST);
        $mockBotAutotest->method('getLineTarget')->willReturn('test_line_target_autotest'); // Unique line target
        $mockBotAutotest->method('hasHumanCharacteristics')->willReturn(true);
        $mockBotAutotest->method('getBotCharacteristics')->willReturn(['Bot char 1 AUTOTEST']);
        $mockBotAutotest->method('getHumanCharacteristics')->willReturn(['Human char 1 AUTOTEST']);
        $mockBotAutotest->method('getConfigRequests')->willReturn(['Default request AUTOTEST']);
        $mockBotAutotest->method('getTriggers')->willReturn([]); // Default to no triggers initially

        // --- Bot Mock for TARGET_ID_FOR_DEFAULT_BEHAVIOR (bot with minimal/default-like config) ---
        $mockBotDefaultBehavior = $this->createMock(Bot::class);
        $mockBotDefaultBehavior->method('getId')->willReturn(self::TARGET_ID_FOR_DEFAULT_BEHAVIOR);
        $mockBotDefaultBehavior->method('getLineTarget')->willReturn('test_line_target_default'); // Unique line target
        $mockBotDefaultBehavior->method('hasHumanCharacteristics')->willReturn(false); // Different from autotest
        $mockBotDefaultBehavior->method('getBotCharacteristics')->willReturn(['Default Bot char BEHAVIOR']);
        $mockBotDefaultBehavior->method('getHumanCharacteristics')->willReturn([]);
        $mockBotDefaultBehavior->method('getConfigRequests')->willReturn(['Basic request BEHAVIOR']);


        // --- Bot Mocks for specific search/GPT scenarios ---
        // It's often cleaner to create specific mocks if their behavior diverges significantly,
        // but for simple cases like different IDs, reusing a base mock is fine.
        // Let's assume $mockBotAutotest can serve for most of these unless specific methods need different returns.
        $mockBotSearchYes = $this->createMock(Bot::class); // Example of a dedicated mock
        $mockBotSearchYes->method('getId')->willReturn("TARGET_ID_AUTOTEST_SEARCH_YES");
        // ... other necessary method stubs for $mockBotSearchYes

        $this->botRepositoryMock->method('findById')
            ->willReturnMap([
                [self::TARGET_ID_AUTOTEST, $mockBotAutotest],
                [self::TARGET_ID_FOR_DEFAULT_BEHAVIOR, $mockBotDefaultBehavior],
                [self::TARGET_ID_THAT_THROWS_EXCEPTION, null],
                ["TARGET_ID_AUTOTEST_SEARCH_YES", $mockBotAutotest], 
                ["TARGET_ID_AUTOTEST_SEARCH_NO", $mockBotAutotest],
                ["TARGET_ID_AUTOTEST_SEARCH_UNEXPECTED", $mockBotAutotest],
                ["TARGET_ID_GETANSWER_SEARCH", $mockBotAutotest], 
                ["TARGET_ID_GETANSWER_NOSEARCH", $mockBotAutotest],
                ["TARGET_ID_GENERATE_QUERY", $mockBotAutotest],
                ["TARGET_ID_GENERATE_QUERY_EMPTY", $mockBotAutotest],
                ["TARGET_ID_GENERATE_QUERY_SHORT", $mockBotAutotest],
                ["TARGET_ID_WEBSEARCH_CONFIGURED", $mockBotAutotest],
            ]);

        // Main instance for most tests
        $this->chatService = new ChatApplicationService(
            self::TARGET_ID_AUTOTEST,
            $this->botRepositoryMock,
            $this->conversationRepositoryMock,
            true // isTest
        );
        $this->setPrivateProperty($this->chatService, 'gpt', $this->gptMock);
        // WebSearchTool is often re-mocked or its properties set per test for specific search scenarios
    }

    public function testConstructorThrowsExceptionWhenBotNotFound(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Bot with ID '" . self::TARGET_ID_THAT_THROWS_EXCEPTION . "' not found.");
        new ChatApplicationService(
            self::TARGET_ID_THAT_THROWS_EXCEPTION,
            $this->botRepositoryMock,
            $this->conversationRepositoryMock,
            true
        );
    }

    public function testGetAnswerWithoutRecentConversation()
    {
        $this->gptMock->method('getAnswer')->willReturn('Mocked Answer');
        $this->conversationRepositoryMock->method('findByBotId')->willReturn([]);

        $this->assertNotEmpty($this->chatService->getAnswer(
            false, // applyRecentConversations
            "今年のクリスマスは何月何日でしょうか？\n昨年のクリスマスとは違うのでしょうか？"
        ));
    }

    public function testGetAnswerWithRecentConversation()
    {
        $mockedConversation = $this->createMock(Conversation::class);
        $this->conversationRepositoryMock->method('findByBotId')
            ->willReturn([$mockedConversation]);
        $this->gptMock->method('getAnswer')->willReturn('Mocked Answer');

        $this->assertNotEmpty($this->chatService->getAnswer(
            true, // applyRecentConversations
            "今年のクリスマスは何月何日でしょうか？\n昨年のクリスマスとは違うのでしょうか？"
        ));
    }

    public function testAskRequestWithoutRecentConversation()
    {
        $this->gptMock->method('getAnswer')->willReturn('Mocked Answer');
        $this->conversationRepositoryMock->method('findByBotId')->willReturn([]);

        $this->assertNotEmpty($this->chatService->askRequest(
            false, // applyRecentConversations
            "今年のクリスマスメッセージを送って"
        ));
    }

    // Refactored context tests: Test getAnswer and inspect context via GPT mock callback
    public function testContextBuildingForBotWithNoHumanChars()
    {
        $chatServiceDefaultBehavior = new ChatApplicationService(
            self::TARGET_ID_FOR_DEFAULT_BEHAVIOR,
            $this->botRepositoryMock,
            $this->conversationRepositoryMock,
            true
        );
        $this->setPrivateProperty($chatServiceDefaultBehavior, 'gpt', $this->gptMock);


        $expectedContextPart = "【話し相手の情報】"; // This should be MISSING
        $this->gptMock->method('getAnswer')
            ->with(
                $this->callback(function ($context) use ($expectedContextPart) {
                    $this->assertStringNotContainsString($expectedContextPart, $context);
                    // Check for default bot chars
                    $this->assertStringContainsString("Default Bot char BEHAVIOR", $context);
                    return true;
                }),
                $this->anything() // message
            )
            ->willReturn("Mocked answer");

        $chatServiceDefaultBehavior->getAnswer(false, "some message");
    }

    public function testContextBuildingForBotWithHumanChars()
    {
        // chatService is already TARGET_ID_AUTOTEST which has human chars
        $expectedContextPart = "【話し相手の情報】"; // This should be PRESENT
        $humanCharPart = "Human char 1 AUTOTEST";
        $this->gptMock->method('getAnswer')
            ->with(
                $this->callback(function ($context) use ($expectedContextPart, $humanCharPart) {
                    $this->assertStringContainsString($expectedContextPart, $context);
                    $this->assertStringContainsString($humanCharPart, $context);
                    return true;
                }),
                $this->anything()
            )
            ->willReturn("Mocked answer");
        $this->chatService->getAnswer(false, "some message");
    }

    public function testContextBuildingWithoutRecentConversations()
    {
        $this->conversationRepositoryMock->method('findByBotId')->willReturn([]);
        
        $expectedContextPart = "【最近の会話内容】"; // This should be MISSING
        $this->gptMock->method('getAnswer')
            ->with(
                $this->callback(function ($context) use ($expectedContextPart) {
                    $this->assertStringNotContainsString($expectedContextPart, $context);
                    return true;
                }),
                $this->anything()
            )
            ->willReturn("Mocked answer");
        $this->chatService->getAnswer(false, "some message"); // applyRecentConversations = false
    }

    public function testContextBuildingWithRecentConversations()
    {
        $mockConversation = $this->createMock(Conversation::class);
        $mockConversation->method('getCreatedAt')->willReturn(Carbon::now());
        $mockConversation->method('getSpeaker')->willReturn('human');
        $mockConversation->method('getContent')->willReturn('Test conversation content');

        $this->conversationRepositoryMock->method('findByBotId')->willReturn([$mockConversation]);

        $expectedContextPart = "【最近の会話内容】"; // This should be PRESENT
        $conversationContentPart = "Test conversation content";
        $this->gptMock->method('getAnswer')
            ->with(
                $this->callback(function ($context) use ($expectedContextPart, $conversationContentPart) {
                    $this->assertStringContainsString($expectedContextPart, $context);
                    $this->assertStringContainsString($conversationContentPart, $context);
                    return true;
                }),
                $this->anything()
            )
            ->willReturn("Mocked answer");
        $this->chatService->getAnswer(true, "some message"); // applyRecentConversations = true
    }


    public function testGetLineTarget_ForAutotestBot()
    {
        // chatService is TARGET_ID_AUTOTEST
        $this->assertSame('test_line_target_autotest', $this->chatService->getLineTarget());
    }

    public function testGetLineTarget_ForDefaultBehaviorBot()
    {
        $chatServiceDefault = new ChatApplicationService(
            self::TARGET_ID_FOR_DEFAULT_BEHAVIOR,
            $this->botRepositoryMock,
            $this->conversationRepositoryMock,
            true 
        );
        $this->assertSame('test_line_target_default', $chatServiceDefault->getLineTarget());
    }

    public function testAddTimerTrigger(): void
    {
        $mockTimerTrigger = $this->createMock(TimerTrigger::class);
        // $mockTimerTrigger->method('getId')->willReturn('trigger123'); // Not strictly needed for this test's focus

        // Get the specific mock Bot instance that chatService will be using
        $botFromRepo = $this->botRepositoryMock->findById(self::TARGET_ID_AUTOTEST);
        
        // Expect Bot::addTrigger to be called
        $botFromRepo->expects($this->once()) // Or ->exactly(1) if you prefer for single call
                    ->method('addTrigger')
                    ->with($mockTimerTrigger) // Assert it's called with the trigger
                    ->willReturn('new_mocked_trigger_id'); // Return value for Bot::addTrigger

        // Expect BotRepository::save to be called with the correct Bot instance
        $this->botRepositoryMock->expects($this->once())
                                ->method('save')
                                ->with($botFromRepo); // Assert it's called with the bot instance

        $this->chatService->addTimerTrigger($mockTimerTrigger);

        // The assertions are on the mock objects' expectations.
        // If you want to assert the return value of addTimerTrigger from ChatApplicationService:
        // $returnedId = $this->chatService->addTimerTrigger($mockTimerTrigger);
        // $this->assertSame('new_mocked_trigger_id', $returnedId);
        // For this, ensure the mock Bot's addTrigger returns what ChatApplicationService should return.
    }
    
    public function testDeleteTrigger(): void
    {
        $triggerIdToDelete = 'trigger_to_delete_123';

        $botFromRepo = $this->botRepositoryMock->findById(self::TARGET_ID_AUTOTEST);
        $botFromRepo->expects($this->once())
                    ->method('deleteTriggerById')
                    ->with($triggerIdToDelete);
        
        $this->botRepositoryMock->expects($this->once())
                                ->method('save')
                                ->with($botFromRepo);
        
        $this->chatService->deleteTrigger($triggerIdToDelete);
    }


    // Helper method for setting private properties
    protected function setPrivateProperty($object, string $propertyName, $value): void
    {
        $reflection = new \ReflectionClass($object);
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true); // In PHP 8.1+, setAccessible is no longer needed for private props of the same class
        $property->setValue($object, $value);
    }

    // Tests for __shouldPerformWebSearch (indirectly, by testing getAnswer)
    public function testGetAnswerFlowWhenShouldPerformWebSearchReturnsTrue(): void
    {
        $userMessage = 'some message requiring search';
        $this->setPrivateProperty($this->chatService, 'googleApiKey', 'dummy_key'); // Enable search logic path
        $this->setPrivateProperty($this->chatService, 'googleCxId', 'dummy_cx');   // Enable search logic path
        $this->setPrivateProperty($this->chatService, 'webSearchTool', $this->webSearchToolMock);


        // Mocking GPT for __shouldPerformWebSearch internal call
        // This specific call is deep inside. We'll mock the GPT calls getAnswer makes.
        // 1. For judging web search
        // 2. For generating query (if search is needed)
        // 3. For final response
        $this->gptMock->expects($this->exactly(3)) // Adjusted based on the flow
            ->method('getAnswer')
            ->willReturnMap([
                [ChatApplicationService::PROMPT_JUDGE_WEB_SEARCH, $userMessage, 'はい'], // Yes, search
                [ChatApplicationService::PROMPT_GENERATE_SEARCH_QUERY, $userMessage, 'search query'], // Generated query
                [$this->isType('string'), $userMessage, 'Final answer with search results'], // Context, message
            ]);
        
        $this->webSearchToolMock->method('search')->willReturn('Mocked search results');

        $this->chatService->getAnswer(true, $userMessage);
        // Assertions are on the mocks (e.g., webSearchToolMock->expects($this->once())->method('search'))
        // Or, more robustly, check the context passed to the final GPT call as in other tests.
    }


    public function testGetAnswerFlowWhenShouldPerformWebSearchReturnsFalse(): void
    {
        $userMessage = 'another message not needing search';
        // Ensure search tool is not called, GPT for query generation is not called.
        $this->setPrivateProperty($this->chatService, 'gpt', $this->gptMock); // Ensure our mock is used

        $this->gptMock->expects($this->exactly(2)) // Judge, Final Answer
            ->method('getAnswer')
            ->willReturnMap([
                [ChatApplicationService::PROMPT_JUDGE_WEB_SEARCH, $userMessage, 'いいえ'], // No search
                // No call for PROMPT_GENERATE_SEARCH_QUERY
                [$this->isType('string'), $userMessage, 'Final answer, no search'], // Context, message
            ]);

        $this->webSearchToolMock->expects($this->never())->method('search');

        $this->chatService->getAnswer(true, $userMessage);
    }


    // Tests for __getContext (related to web search results) - by checking context in getAnswer
    public function testContextIncludesWebSearchResultsWhenProvidedViaGetAnswer(): void
    {
        $userMessage = "search this";
        $searchResults = "Found web information: A, B, C.";
        $this->setPrivateProperty($this->chatService, 'googleApiKey', 'dummy_key');
        $this->setPrivateProperty($this->chatService, 'googleCxId', 'dummy_cx');
        $this->setPrivateProperty($this->chatService, 'webSearchTool', $this->webSearchToolMock);

        $this->gptMock->method('getAnswer')
            ->will($this->onConsecutiveCalls(
                'はい', // Judge web search
                'search query for this', // Generate query
                'Final Answer' // Generate final answer
            ));
        
        $this->webSearchToolMock->method('search')->willReturn($searchResults);

        // Override the last gptMock expectation to inspect context
        $this->gptMock->expects($this->atLeastOnce()) // Or $this->exactly(3) if precise
            ->method('getAnswer')
            ->with(
                $this->callback(function ($contextArg) use ($searchResults) {
                    // This callback will be invoked for all calls to gpt->getAnswer.
                    // We are interested in the one that contains the search results.
                    if (str_contains($contextArg, "【Web検索結果】")) {
                        $this->assertStringContainsString($searchResults, $contextArg);
                    }
                    return true; // Allow the test to proceed
                }),
                $this->anything()
            );


        $this->chatService->getAnswer(true, $userMessage);
    }

    public function testContextExcludesWebSearchResultsWhenNullViaGetAnswer(): void
    {
        $userMessage = "no search this";
         // Ensure API keys are null or webSearchTool is null, or GPT says "いいえ"
        $this->setPrivateProperty($this->chatService, 'googleApiKey', null); // Simulate no config for search


        $this->gptMock->method('getAnswer')
            ->will($this->onConsecutiveCalls(
                'いいえ', // Judge web search -> No
                'Final Answer' // Final answer generation
            ));
        // webSearchToolMock->search should not be called.
        // PROMPT_GENERATE_SEARCH_QUERY should not be called.

        $this->gptMock->expects($this->atLeastOnce())
            ->method('getAnswer')
            ->with(
                $this->callback(function ($contextArg) {
                    if (!str_contains($contextArg, ChatApplicationService::PROMPT_JUDGE_WEB_SEARCH) &&
                        !str_contains($contextArg, ChatApplicationService::PROMPT_GENERATE_SEARCH_QUERY)
                    ) { // Only inspect the final context
                        $this->assertStringNotContainsString("【Web検索結果】", $contextArg);
                        $this->assertStringNotContainsString("<web_search_results>", $contextArg);
                    }
                    return true;
                }),
                $this->anything()
            );
        
        $this->chatService->getAnswer(true, $userMessage);
    }


    public function testGetAnswerUsesWebSearchToolInstanceWhenConfigured(): void
    {
        $userMessage = "Tell me about cats.";
        $dummySearchQuery = "cats information";
        $mockedSearchResults = "Web Search Results:\n- Title: Cats are great. Snippet: Yes they are.";
        $expectedFinalAnswer = "Based on my research, cats are indeed great.";

        $this->setPrivateProperty($this->chatService, 'googleApiKey', "DUMMY_API_KEY");
        $this->setPrivateProperty($this->chatService, 'googleCxId', "DUMMY_CX_ID");
        $this->setPrivateProperty($this->chatService, 'webSearchTool', $this->webSearchToolMock);

        $this->gptMock->expects($this->exactly(3))
            ->method('getAnswer')
            ->willReturnMap([
                [ChatApplicationService::PROMPT_JUDGE_WEB_SEARCH, $userMessage, 'はい'],
                [ChatApplicationService::PROMPT_GENERATE_SEARCH_QUERY, $userMessage, $dummySearchQuery],
                [$this->callback(function ($context) use ($mockedSearchResults) {
                    $this->assertStringContainsString("【Web検索結果】", $context);
                    $this->assertStringContainsString($mockedSearchResults, $context);
                    return true;
                }), $userMessage, $expectedFinalAnswer],
            ]);

        $this->webSearchToolMock->expects($this->once())
            ->method('search')
            ->with($dummySearchQuery, "DUMMY_CX_ID") 
            ->willReturn($mockedSearchResults);
        
        $actualAnswer = $this->chatService->getAnswer(true, $userMessage);
        $this->assertSame($expectedFinalAnswer, $actualAnswer);
    }

    // Tests for __generateSearchQuery (indirectly by testing getAnswer)
    public function testGetAnswerCorrectlyUsesGeneratedSearchQuery(): void
    {
        $userMessage = "What is the weather like tomorrow in Tokyo?";
        $expectedQuery = "weather tomorrow Tokyo";

        $this->setPrivateProperty($this->chatService, 'googleApiKey', 'dummy_key');
        $this->setPrivateProperty($this->chatService, 'googleCxId', 'dummy_cx');
        $this->setPrivateProperty($this->chatService, 'webSearchTool', $this->webSearchToolMock);

        $this->gptMock->method('getAnswer')
            ->will($this->onConsecutiveCalls(
                'はい',           // Judge search: Yes
                $expectedQuery,   // Generate query: "weather tomorrow Tokyo"
                'Final answer based on Tokyo weather' // Final response
            ));
        
        $this->webSearchToolMock->expects($this->once())
                                ->method('search')
                                ->with($expectedQuery, $this->anything()) // Assert this is called with the generated query
                                ->willReturn('Tokyo weather data');

        $this->chatService->getAnswer(true, $userMessage);
    }
    
    public function testGetAnswerFallbackToOriginalMessageForSearchIfGptQueryIsEmpty(): void
    {
        $userMessage = "A complex question with no easy keywords.";
        
        $this->setPrivateProperty($this->chatService, 'googleApiKey', 'dummy_key');
        $this->setPrivateProperty($this->chatService, 'googleCxId', 'dummy_cx');
        $this->setPrivateProperty($this->chatService, 'webSearchTool', $this->webSearchToolMock);

        $this->gptMock->method('getAnswer')
            ->will($this->onConsecutiveCalls(
                'はい', // Judge search: Yes
                '',     // Generate query: Empty response
                'Final answer based on complex question'
            ));

        $this->webSearchToolMock->expects($this->once())
                                ->method('search')
                                ->with($userMessage, $this->anything()) // Should fallback to original message
                                ->willReturn('Results for complex question');
        
        $this->chatService->getAnswer(true, $userMessage);
    }

    public function testGetAnswerFallbackToOriginalMessageForSearchIfGptQueryIsTooShort(): void
    {
        $userMessage = "Another question.";

        $this->setPrivateProperty($this->chatService, 'googleApiKey', 'dummy_key');
        $this->setPrivateProperty($this->chatService, 'googleCxId', 'dummy_cx');
        $this->setPrivateProperty($this->chatService, 'webSearchTool', $this->webSearchToolMock);
        
        $this->gptMock->method('getAnswer')
            ->will($this->onConsecutiveCalls(
                'はい', // Judge search: Yes
                'a',    // Generate query: Too short
                'Final answer for another question'
            ));

        $this->webSearchToolMock->expects($this->once())
                                ->method('search')
                                ->with($userMessage, $this->anything()) // Should fallback to original message
                                ->willReturn('Results for another question');

        $this->chatService->getAnswer(true, $userMessage);
    }
}
