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

    // mockSearchResults is an array of associative arrays, e.g., [['title' => 'T1', 'snippet' => 'S1'], ...]
    // or special strings like 'MALFORMED_ITEM' or 'ITEM_WITHOUT_TITLE_SNIPPET'
    private function createMockApiResponse(array $mockSearchResults): ResponsesCreateResponse
    {
        $mockApiResponse = $this->createMock(ResponsesCreateResponse::class);
        $outputItems = [];

        foreach ($mockSearchResults as $result) {
            if (is_array($result)) {
                $item = new \stdClass();
                if (isset($result['title'])) {
                    $item->title = $result['title'];
                }
                if (isset($result['snippet'])) {
                    $item->snippet = $result['snippet'];
                }
                // For testing fallbacks, e.g., if snippet is missing, description might be used
                if (isset($result['description']) && !isset($item->snippet)) {
                    $item->description = $result['description'];
                }
                if (isset($result['text']) && !isset($item->snippet) && !isset($item->description)) {
                    $item->text = $result['text'];
                }
                $outputItems[] = $item;
            } elseif ($result === 'MALFORMED_ITEM_NOT_OBJECT_OR_ARRAY') {
                $outputItems[] = "just a string"; // Simulate an item that's not an object/array
            } elseif ($result === 'ITEM_WITH_NULL_VALUES') {
                 $item = new \stdClass();
                 $item->title = null;
                 $item->snippet = null;
                 $outputItems[] = $item;
            } elseif ($result === 'ITEM_WITH_EMPTY_STRINGS') {
                 $item = new \stdClass();
                 $item->title = "";
                 $item->snippet = "";
                 $outputItems[] = $item;
            }
        }
        
        $mockApiResponse->output = $outputItems;

        // Mock other necessary properties of ResponsesCreateResponse
        $mockApiResponse->id = 'resp-test123';
        $mockApiResponse->object = 'response'; 
        $mockApiResponse->created = time();
        $mockApiResponse->model = $this->testOpenAiModel;

        return $mockApiResponse;
    }

    public function testSearchSuccessfulMultipleResults(): void
    {
        $query = "test query";
        $numResults = 2;

        $apiOutputContents = [
            ['title' => 'Title 1', 'snippet' => 'Snippet 1.'],
            ['title' => 'Title 2', 'snippet' => 'Snippet 2.'],
        ];
        $mockApiResponse = $this->createMockApiResponse($apiOutputContents);

        $this->mockResponsesResource->expects($this->once())
            ->method('create')
            ->willReturn($mockApiResponse);

        $expectedSummary = "\n- Title: Title 1\nSnippet: Snippet 1.\n\n- Title: Title 2\nSnippet: Snippet 2.";
        
        $actualSummary = $this->webSearchTool->search($query, $numResults);
        $this->assertSame($expectedSummary, $actualSummary);
    }

    public function testSearchSuccessfulSingleResult(): void
    {
        $query = "single result query";
        $numResults = 1;

        $apiOutputContents = [
            ['title' => 'Solo Title', 'snippet' => 'Solo Snippet.'],
        ];
        $mockApiResponse = $this->createMockApiResponse($apiOutputContents);

        $this->mockResponsesResource->expects($this->once())
            ->method('create')
            ->willReturn($mockApiResponse);

        $expectedSummary = "\n- Title: Solo Title\nSnippet: Solo Snippet.";
        
        $actualSummary = $this->webSearchTool->search($query, $numResults);
        $this->assertSame($expectedSummary, $actualSummary);
    }
    
    public function testSearchSuccessfulUsesDescriptionAsFallbackForSnippet(): void
    {
        $query = "description fallback";
        $apiOutputContents = [
            ['title' => 'Title D', 'description' => 'Description as snippet.'],
        ];
        $mockApiResponse = $this->createMockApiResponse($apiOutputContents);
        $this->mockResponsesResource->method('create')->willReturn($mockApiResponse);
        $expected = "\n- Title: Title D\nSnippet: Description as snippet.";
        $this->assertSame($expected, $this->webSearchTool->search($query, 1));
    }

    public function testSearchSuccessfulUsesTextAsFallbackForSnippet(): void
    {
        $query = "text fallback";
        $apiOutputContents = [
            ['title' => 'Title T', 'text' => 'Text as snippet.'],
        ];
        $mockApiResponse = $this->createMockApiResponse($apiOutputContents);
        $this->mockResponsesResource->method('create')->willReturn($mockApiResponse);
        $expected = "\n- Title: Title T\nSnippet: Text as snippet.";
        $this->assertSame($expected, $this->webSearchTool->search($query, 1));
    }

    public function testSearchSuccessfulWithFewerResultsThanReturnedByApi(): void
    {
        $query = "less results query";
        $numResults = 1; // Request 1

        $apiOutputContents = [
            ['title' => 'Title A', 'snippet' => 'Snippet A.'],
            ['title' => 'Title B', 'snippet' => 'Snippet B.'], // API returns 2
        ];
        $mockApiResponse = $this->createMockApiResponse($apiOutputContents);

        $this->mockResponsesResource->expects($this->once())
            ->method('create')
            ->willReturn($mockApiResponse);

        $expectedSummary = "\n- Title: Title A\nSnippet: Snippet A."; // Should only take numResults

        $actualSummary = $this->webSearchTool->search($query, $numResults);
        $this->assertSame($expectedSummary, $actualSummary);
    }

    public function testSearchSuccessfulWithFewerResultsThanRequestedButApiReturnsEvenFewer(): void
    {
        $query = "even less results query";
        $numResults = 3; // Request 3

        $apiOutputContents = [
            ['title' => 'Only Title', 'snippet' => 'The only snippet.'], // API returns only 1
        ];
        $mockApiResponse = $this->createMockApiResponse($apiOutputContents);
        
        $this->mockResponsesResource->expects($this->once())
            ->method('create')
            ->willReturn($mockApiResponse);

        $expectedSummary = "\n- Title: Only Title\nSnippet: The only snippet.";
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

        // This now correctly points to the "Could not extract..." because the parsing logic for structured content fails on empty.
        $expectedMessage = "Could not extract useful information from web search results for: " . htmlspecialchars($query) . ". The response might not contain suitable structured content.";
        $actualMessage = $this->webSearchTool->search($query);
        $this->assertSame($expectedMessage, $actualMessage);
    }

    public function testSearchReturnsCouldNotExtractIfResultItemIsNotObjectOrArray(): void
    {
        $query = "malformed item query";
        
        $apiOutputContents = ['MALFORMED_ITEM_NOT_OBJECT_OR_ARRAY']; // Special string for createMockApiResponse
        $mockApiResponse = $this->createMockApiResponse($apiOutputContents);

        $this->mockResponsesResource->expects($this->once())
            ->method('create')
            ->willReturn($mockApiResponse);
        
        $expectedMessage = "Could not extract useful information from web search results for: " . htmlspecialchars($query) . ". The response might not contain suitable structured content.";
        $actualMessage = $this->webSearchTool->search($query);
        $this->assertSame($expectedMessage, $actualMessage);
    }

    public function testSearchReturnsCouldNotExtractIfResultItemHasNullTitleAndSnippet(): void
    {
        $query = "null title snippet query";
        
        $apiOutputContents = ['ITEM_WITH_NULL_VALUES']; // Special string for createMockApiResponse
        $mockApiResponse = $this->createMockApiResponse($apiOutputContents);

        $this->mockResponsesResource->expects($this->once())
            ->method('create')
            ->willReturn($mockApiResponse);
        
        $expectedMessage = "Could not extract useful information from web search results for: " . htmlspecialchars($query) . ". The response might not contain suitable structured content.";
        $actualMessage = $this->webSearchTool->search($query);
        $this->assertSame($expectedMessage, $actualMessage);
    }

    public function testSearchReturnsCouldNotExtractIfResultItemHasEmptyStringTitleAndSnippet(): void
    {
        $query = "empty string title snippet query";
        
        $apiOutputContents = ['ITEM_WITH_EMPTY_STRINGS']; // Special string for createMockApiResponse
        $mockApiResponse = $this->createMockApiResponse($apiOutputContents);

        $this->mockResponsesResource->expects($this->once())
            ->method('create')
            ->willReturn($mockApiResponse);
        
        // Even if title/snippet are empty strings, the parser might still pick them up if not explicitly checking for empty.
        // The WebSearchTool's parser currently does `if ($title && $snippet)`. Empty strings are falsy.
        $expectedMessage = "Could not extract useful information from web search results for: " . htmlspecialchars($query) . ". The response might not contain suitable structured content.";
        $actualMessage = $this->webSearchTool->search($query);
        $this->assertSame($expectedMessage, $actualMessage);
    }
    
    public function testSearchOnlyIncludesSnippetIfTitleMissing(): void
    {
        $query = "missing title query";
        $apiOutputContents = [
            ['snippet' => 'Only snippet here.'],
        ];
        $mockApiResponse = $this->createMockApiResponse($apiOutputContents);
        $this->mockResponsesResource->method('create')->willReturn($mockApiResponse);
        $expected = "\n- Snippet: Only snippet here.";
        $this->assertSame($expected, $this->webSearchTool->search($query, 1));
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
