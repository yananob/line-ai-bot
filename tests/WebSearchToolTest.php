<?php

declare(strict_types=1);

// namespace MyApp\Tests; // Assuming this is commented out in the original as well

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use MyApp\WebSearchTool;

// OpenAI specific classes
use OpenAI\Client as OpenAiClient;
use OpenAI\Resources\Responses; // For mocking $client->responses()
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

        $this->mockOpenAiClient = $this->createMock(OpenAiClient::class);
        $this->mockResponsesResource = $this->createMock(Responses::class);

        // Configure the mock OpenAiClient to return the mock Responses resource
        $this->mockOpenAiClient->method('responses')->willReturn($this->mockResponsesResource);

        // Instantiate WebSearchTool with the mocked OpenAI client and a test model name
        $this->webSearchTool = new WebSearchTool($this->mockOpenAiClient, $this->testOpenAiModel);
    }

    private function createMockApiResponse(array $outputContents): ResponsesCreateResponse
    {
        $mockApiResponse = $this->createMock(ResponsesCreateResponse::class);
        $outputItems = [];
        foreach ($outputContents as $contentTexts) {
            $contentObjects = [];
            if (is_array($contentTexts)) { // Expecting an array of text strings for each output item
                foreach ($contentTexts as $text) {
                    $contentObjects[] = (object)['type' => 'output_text', 'text' => $text];
                }
            } elseif (is_string($contentTexts) && $contentTexts === 'MALFORMED_CONTENT_ITEM_TYPE') {
                 $contentObjects[] = (object)['type' => 'other_type', 'text' => 'Not useful'];
            } elseif (is_string($contentTexts) && $contentTexts === 'EMPTY_TEXT_CONTENT_ITEM') {
                 $contentObjects[] = (object)['type' => 'output_text', 'text' => ''];
            }


            $outputItem = new \stdClass();
            $outputItem->content = $contentObjects;
            $outputItems[] = $outputItem;
        }
        
        // The 'output' property is public in the actual response object, so we can set it directly on the mock.
        // If it were a method, we'd do $mockApiResponse->method('output')->willReturn($outputItems);
        $mockApiResponse->output = $outputItems;

        // Mock other necessary properties of ResponsesCreateResponse
        $mockApiResponse->id = 'resp-test123';
        $mockApiResponse->object = 'response'; // Or the actual object type string
        $mockApiResponse->created = time();
        $mockApiResponse->model = $this->testOpenAiModel;

        return $mockApiResponse;
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

    public function testSearchReturnsNoResultsFoundWhenApiReturnsEmptyOutputArray(): void
    {
        $query = "no results query empty output";
        
        $mockApiResponse = $this->createMockApiResponse([]); // Empty output array

        $this->mockResponsesResource->expects($this->once())
            ->method('create')
            ->willReturn($mockApiResponse);

        $expectedMessage = "No web search results found or unexpected response structure for: " . htmlspecialchars($query);
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
        $expectedMessage = "Could not extract useful information from web search results for: " . htmlspecialchars($query) . ". The response might not contain suitable text content.";
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
        
        $expectedMessage = "Could not extract useful information from web search results for: " . htmlspecialchars($query) . ". The response might not contain suitable text content.";
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
        
        $expectedMessage = "Could not extract useful information from web search results for: " . htmlspecialchars($query) . ". The response might not contain suitable text content.";
        $actualMessage = $this->webSearchTool->search($query);
        $this->assertSame($expectedMessage, $actualMessage);
    }


    public function testSearchHandlesOpenAiApiException(): void
    {
        $query = "openai api exception query";
        $exceptionMessage = "OpenAI API error occurred (responses)";

        $this->mockResponsesResource->expects($this->once())
            ->method('create')
            ->willThrowException(new OpenAIErrorException(['message' => $exceptionMessage, 'type' => 'api_error']));

        $expectedMessage = "Error performing web search: AI service returned an error. " . $exceptionMessage;
        $actualMessage = $this->webSearchTool->search($query);
        $this->assertSame($expectedMessage, $actualMessage);
    }

    public function testSearchHandlesOpenAiTransporterException(): void
    {
        $query = "openai transporter exception query";
        $exceptionMessage = "Network issue with OpenAI (responses)";

        $this->mockResponsesResource->expects($this->once())
            ->method('create')
            ->willThrowException(new OpenAITransporterException($exceptionMessage));

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
