<?php

declare(strict_types=1);

// namespace MyApp\Tests; // Assuming this is commented out in the original as well

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use MyApp\WebSearchTool;

// OpenAI specific classes
use OpenAI\Contracts\ClientContract as OpenAiClientContract;
use OpenAI\Contracts\Resources\ResponsesContract;
use OpenAI\Responses\Responses\CreateResponse as ResponsesCreateResponse; // For the actual response type
// Note: No longer need OpenAI\Resources\Chat or OpenAI\Responses\Chat\*

use OpenAI\Exceptions\ErrorException as OpenAIErrorException;
use OpenAI\Exceptions\TransporterException as OpenAITransporterException;

class WebSearchToolTest extends TestCase
{
    private MockObject $mockOpenAiClient;
    private MockObject $mockResponsesResource; // Mock for OpenAI\Resources\Responses
    private WebSearchTool $webSearchTool;
    private string $testOpenAiModel = 'gpt-4o'; // Model that would support responses()->create()

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockOpenAiClient = $this->createMock(\OpenAI\Contracts\ClientContract::class);
        $this->mockResponsesResource = $this->createMock(\OpenAI\Contracts\Resources\ResponsesContract::class);

        // Configure the mock OpenAiClient to return the mock Responses resource
        $this->mockOpenAiClient->method('responses')->willReturn($this->mockResponsesResource);

        // Instantiate WebSearchTool with the mocked OpenAI client and a test model name
        $this->webSearchTool = new WebSearchTool($this->mockOpenAiClient, $this->testOpenAiModel);
    }

    private function createMockApiResponse(array $outputContents): \OpenAI\Responses\Responses\CreateResponse
    {
        $outputMessages = [];
        $messageIdCounter = 0;

        foreach ($outputContents as $contentItemSnippets) {
            $outputTextElements = [];
            if (is_array($contentItemSnippets)) {
                foreach ($contentItemSnippets as $snippetText) {
                    if ($snippetText === 'MALFORMED_CONTENT_ITEM_TYPE') {
                        // This type will be handled by OutputMessage::from but ignored by WebSearchTool's parsing logic
                        $outputTextElements[] = ['type' => 'refusal', 'refusal' => 'Simulated refusal, should be ignored', 'annotations' => []];
                    } elseif ($snippetText === 'EMPTY_TEXT_CONTENT_ITEM') {
                        $outputTextElements[] = ['type' => 'output_text', 'text' => '', 'annotations' => []];
                    } else {
                        $outputTextElements[] = ['type' => 'output_text', 'text' => $snippetText, 'annotations' => []];
                    }
                }
            } elseif ($contentItemSnippets === 'EMPTY_OUTPUT_ITEM') { // Special case for an output item with no content
                 $outputMessages[] = [
                    'type' => 'message',
                    'id' => 'msg_' . (++$messageIdCounter),
                    'role' => 'assistant',
                    'status' => 'completed',
                    'content' => [], // Empty content array
                 ];
                 continue; // Move to next outputContent
            }

            $outputMessages[] = [
                'type' => 'message',
                'id' => 'msg_' . (++$messageIdCounter),
                'role' => 'assistant',
                'status' => 'completed',
                'content' => $outputTextElements,
            ];
        }
        
        $attributes = [
            'id' => 'resp-test123',
            'object' => 'response',
            'created_at' => time(),
            'status' => 'completed',
            'error' => null,
            'incomplete_details' => null,
            'instructions' => null,
            'max_output_tokens' => null,
            'model' => $this->testOpenAiModel,
            'output' => $outputMessages,
            'parallel_tool_calls' => false,
            'previous_response_id' => null,
            'reasoning' => null,
            'store' => false,
            'temperature' => null,
            'text' => ['format' => ['type' => 'text', 'text' => ($outputMessages ? 'Formatted text if applicable' : '')]],
            'tool_choice' => 'none',
            'tools' => [],
            'top_p' => null,
            'truncation' => null,
            'usage' => null,
            'user' => null,
            'metadata' => [],
        ];

        // Create a dummy MetaInformation object
        $meta = \OpenAI\Responses\Meta\MetaInformation::from([]);

        return \OpenAI\Responses\Responses\CreateResponse::from($attributes, $meta);
    }

    public function testSearchSuccessful(): void
    {
        $query = "test query";
        $numResults = 2;

        // Each inner array represents an $outputItem's content, with strings being individual 'text' fields
        $apiOutputContents = [
            ["First search result snippet."], // Corresponds to $outputItem1->content
            ["Second search result snippet."] // Corresponds to $outputItem2->content
        ];
        $mockApiResponse = $this->createMockApiResponse($apiOutputContents);

        $this->mockResponsesResource->expects($this->once())
            ->method('create')
            ->willReturn($mockApiResponse);

        $expectedSummary = "\n- Snippet: First search result snippet.\n- Snippet: Second search result snippet.";
        
        $actualSummary = $this->webSearchTool->search($query, $numResults);
        $this->assertSame($expectedSummary, $actualSummary);
    }
    
    public function testSearchSuccessfulHandlesMultipleTextsInOneOutputItem(): void
    {
        $query = "multi text query";
        $numResults = 2;

        $apiOutputContents = [
            ["First snippet.", "Second snippet from same output item."]
        ];
        $mockApiResponse = $this->createMockApiResponse($apiOutputContents);

        $this->mockResponsesResource->expects($this->once())
            ->method('create')
            ->willReturn($mockApiResponse);

        $expectedSummary = "\n- Snippet: First snippet.\n- Snippet: Second snippet from same output item.";
        
        $actualSummary = $this->webSearchTool->search($query, $numResults);
        $this->assertSame($expectedSummary, $actualSummary);
    }

    public function testSearchSuccessfulWithFewerResultsThanReturnedByApi(): void
    {
        $query = "less results query";
        $numResults = 1; // Request 1

        $apiOutputContents = [
            ["First snippet."],
            ["Second snippet."] // API returns 2 snippets
        ];
        $mockApiResponse = $this->createMockApiResponse($apiOutputContents);

        $this->mockResponsesResource->expects($this->once())
            ->method('create')
            ->willReturn($mockApiResponse);

        $expectedSummary = "\n- Snippet: First snippet."; // Should only take numResults

        $actualSummary = $this->webSearchTool->search($query, $numResults);
        $this->assertSame($expectedSummary, $actualSummary);
    }

    public function testSearchSuccessfulWithFewerResultsThanRequestedButApiReturnsEvenFewer(): void
    {
        $query = "even less results query";
        $numResults = 3; // Request 3

        $apiOutputContents = [
            ["The only snippet."] // API returns only 1
        ];
        $mockApiResponse = $this->createMockApiResponse($apiOutputContents);
        
        $this->mockResponsesResource->expects($this->once())
            ->method('create')
            ->willReturn($mockApiResponse);

        $expectedSummary = "\n- Snippet: The only snippet.";
        $actualSummary = $this->webSearchTool->search($query, $numResults);
        $this->assertSame($expectedSummary, $actualSummary);
    }

    public function testSearchQueryIncludesJapanesePhraseForRecentResults(): void
    {
        $baseQuery = "original query";
        $numResults = 1;
        // This is the Japanese phrase that should be appended, including the leading space.
        $expectedPhraseSuffix = " 検索結果はできるだけ新しいものを使うようにしてください。";

        // Mock the API response
        $apiOutputContents = [["Test snippet."]];
        $mockApiResponse = $this->createMockApiResponse($apiOutputContents);

        $this->mockResponsesResource->expects($this->once())
            ->method('create')
            ->with($this->callback(function ($params) use ($baseQuery, $expectedPhraseSuffix) {
                $this->assertArrayHasKey('input', $params, "Params array must have 'input' key.");
                $this->assertStringEndsWith($expectedPhraseSuffix, $params['input'], "Query input should end with the Japanese phrase.");
                // Also check if the base query is at the beginning of the input
                $this->assertStringStartsWith($baseQuery, $params['input'], "Query input should start with the base query.");
                // Check that the full query is the base query + the suffix
                $this->assertEquals($baseQuery . $expectedPhraseSuffix, $params['input'], "Full query string is not as expected.");
                return true; // Callback must return true if assertions pass
            }))
            ->willReturn($mockApiResponse);

        // Call the search method
        $this->webSearchTool->search($baseQuery, $numResults);
        // No need to assert the return value of search() itself for this test,
        // as we are focused on the arguments passed to the mocked 'create' method.
    }

    public function testSearchReturnsNoResultsFoundWhenApiReturnsEmptyOutputArray(): void
    {
        $query = "no results query empty output";
        
        $mockApiResponse = $this->createMockApiResponse([]); // Empty output array

        $this->mockResponsesResource->expects($this->once())
            ->method('create')
            ->willReturn($mockApiResponse);

        $expectedMessage = "No web search results found or unexpected response structure for: " . htmlspecialchars($query . " 検索結果はできるだけ新しいものを使うようにしてください。");
        $actualMessage = $this->webSearchTool->search($query);
        $this->assertSame($expectedMessage, $actualMessage);
    }
    
    public function testSearchReturnsNoResultsFoundWhenApiReturnsEmptyContentArrayInOutputItem(): void
    {
        $query = "no results query empty content";
        
        $mockApiResponse = $this->createMockApiResponse([[]]); // Output item with empty content array

        $this->mockResponsesResource->expects($this->once())
            ->method('create')
            ->willReturn($mockApiResponse);

        // This will actually lead to "Could not extract useful information..." because output is not empty, but content parsing fails
        $expectedMessage = "Could not extract useful information from web search results for: " . htmlspecialchars($query . " 検索結果はできるだけ新しいものを使うようにしてください。") . ". The response might not contain suitable text content.";
        $actualMessage = $this->webSearchTool->search($query);
        $this->assertSame($expectedMessage, $actualMessage);
    }


    public function testSearchReturnsCouldNotExtractUsefulInfoFromMalformedContentType(): void
    {
        $query = "malformed content type query";
        
        // Pass a special string to trigger malformed content item type in createMockApiResponse
        $apiOutputContents = [ ['MALFORMED_CONTENT_ITEM_TYPE'] ];
        $mockApiResponse = $this->createMockApiResponse($apiOutputContents);

        $this->mockResponsesResource->expects($this->once())
            ->method('create')
            ->willReturn($mockApiResponse);
        
        $expectedMessage = "Could not extract useful information from web search results for: " . htmlspecialchars($query . " 検索結果はできるだけ新しいものを使うようにしてください。") . ". The response might not contain suitable text content.";
        $actualMessage = $this->webSearchTool->search($query);
        $this->assertSame($expectedMessage, $actualMessage);
    }
    
    public function testSearchReturnsCouldNotExtractUsefulInfoFromEmptyTextInContentItem(): void
    {
        $query = "empty text content query";
        
        $apiOutputContents = [ ['EMPTY_TEXT_CONTENT_ITEM'] ]; // Special string for empty text
        $mockApiResponse = $this->createMockApiResponse($apiOutputContents);

        $this->mockResponsesResource->expects($this->once())
            ->method('create')
            ->willReturn($mockApiResponse);
        
        $expectedMessage = "Could not extract useful information from web search results for: " . htmlspecialchars($query . " 検索結果はできるだけ新しいものを使うようにしてください。") . ". The response might not contain suitable text content.";
        $actualMessage = $this->webSearchTool->search($query);
        $this->assertSame($expectedMessage, $actualMessage);
    }


    public function testSearchHandlesOpenAiApiException(): void
    {
        $query = "openai api exception query";
        $exceptionMessage = "OpenAI API error occurred (responses)";
        $errorCode = "test_error_code";
        $errorType = "api_error";
        $statusCode = 500;

        $errorContents = [
            'message' => $exceptionMessage,
            'type' => $errorType,
            'code' => $errorCode,
        ];

        $this->mockResponsesResource->expects($this->once())
            ->method('create')
            ->willThrowException(new OpenAIErrorException($errorContents, $statusCode));

        $expectedMessage = "Error performing web search: AI service returned an error. " . $exceptionMessage;
        $actualMessage = $this->webSearchTool->search($query);
        $this->assertSame($expectedMessage, $actualMessage);
    }

    public function testSearchHandlesOpenAiTransporterException(): void
    {
        $query = "openai transporter exception query";
        $exceptionMessage = "Network issue with OpenAI (responses)";

        // Create a stub for Psr\Http\Client\ClientExceptionInterface
        $clientExceptionStub = new class($exceptionMessage) extends \Exception implements \Psr\Http\Client\ClientExceptionInterface {
            public function __construct(string $message) {
                parent::__construct($message);
            }
        };

        $this->mockResponsesResource->expects($this->once())
            ->method('create')
            ->willThrowException(new OpenAITransporterException($clientExceptionStub));

        $expectedMessage = "Error performing web search: Could not connect to the AI service. " . $exceptionMessage;
        $actualMessage = $this->webSearchTool->search($query);
        $this->assertSame($expectedMessage, $actualMessage);
    }
    
    public function testSearchReturnsErrorForEmptyQuery(): void
    {
        $this->assertSame(
            "Error: Search query is empty.",
            $this->webSearchTool->search("")
        );
    }

    public function testSearchReturnsErrorForZeroNumResults(): void
    {
        $this->assertSame(
            "Error: Number of results must be positive.",
            $this->webSearchTool->search("test query", 0)
        );
    }

    public function testSearchReturnsErrorForNegativeNumResults(): void
    {
        $this->assertSame(
            "Error: Number of results must be positive.",
            $this->webSearchTool->search("test query", -1)
        );
    }
}
