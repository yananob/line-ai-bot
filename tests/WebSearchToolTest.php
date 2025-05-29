<?php

declare(strict_types=1);

// namespace MyApp\Tests; // Assuming this is commented out in the original as well

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use MyApp\WebSearchTool;

// OpenAI specific classes
use OpenAI\Client as OpenAiClient;
use OpenAI\Resources\Chat;
use OpenAI\Responses\Chat\CreateResponse;
use OpenAI\Responses\Chat\CreateResponseChoice;
use OpenAI\Responses\Chat\CreateResponseMessage;
use OpenAI\Exceptions\ErrorException as OpenAIErrorException;
use OpenAI\Exceptions\TransporterException as OpenAITransporterException;

class WebSearchToolTest extends TestCase
{
    private MockObject $mockOpenAiClient;
    private MockObject $mockChatResource; // Mock for OpenAI\Resources\Chat
    private WebSearchTool $webSearchTool;
    private string $testOpenAiModel = 'gpt-3.5-turbo';

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockOpenAiClient = $this->createMock(OpenAiClient::class);
        $this->mockChatResource = $this->createMock(Chat::class);

        // Configure the mock OpenAiClient to return the mock Chat resource
        $this->mockOpenAiClient->method('chat')->willReturn($this->mockChatResource);

        // Instantiate WebSearchTool with the mocked OpenAI client and a test model name
        $this->webSearchTool = new WebSearchTool($this->mockOpenAiClient, $this->testOpenAiModel);
    }

    private function createMockApiResponse(string $content): CreateResponse
    {
        $mockMessage = $this->createMock(CreateResponseMessage::class);
        // The 'content' property is public in openai-php/client v0.8.x CreateResponseMessage
        $mockMessage->content = $content;

        $mockChoice = $this->createMock(CreateResponseChoice::class);
        // The 'message' property is public
        $mockChoice->message = $mockMessage;
        // The 'index' property is public
        $mockChoice->index = 0;
         // The 'finishReason' property is public
        $mockChoice->finishReason = 'stop';


        $mockApiResponse = $this->createMock(CreateResponse::class);
        // The 'choices' property is public
        $mockApiResponse->choices = [$mockChoice];
        // Other public properties if needed for full mock, e.g. id, object, created, model
        $mockApiResponse->id = 'chatcmpl-test';
        $mockApiResponse->object = 'chat.completion';
        $mockApiResponse->created = time();
        $mockApiResponse->model = $this->testOpenAiModel;

        return $mockApiResponse;
    }

    public function testSearchSuccessful(): void
    {
        $query = "test query";
        $numResults = 2;

        $apiResponseContent = "Title: Title 1\nSnippet: Snippet for item 1.\n\nTitle: Title 2\nSnippet: Snippet for item 2";
        $mockApiResponse = $this->createMockApiResponse($apiResponseContent);

        $this->mockChatResource->expects($this->once())
            ->method('create')
            // We can use a callback to assert parameters if needed, e.g., model, messages
            ->willReturn($mockApiResponse);

        $expectedSummary = "\n- Title: Title 1. Snippet: Snippet for item 1.\n- Title: Title 2. Snippet: Snippet for item 2.";
        
        $actualSummary = $this->webSearchTool->search($query, $numResults);
        $this->assertSame($expectedSummary, $actualSummary);
    }
    
    public function testSearchSuccessfulWithFewerResultsThanReturnedByApi(): void
    {
        $query = "less results query";
        $numResults = 1; // Request 1, API returns 2

        $apiResponseContent = "Title: Title A\nSnippet: Snippet A.\n\nTitle: Title B\nSnippet: Snippet B.";
        $mockApiResponse = $this->createMockApiResponse($apiResponseContent);

        $this->mockChatResource->expects($this->once())
            ->method('create')
            ->willReturn($mockApiResponse);

        // parseAndFormatOpenAIResponse will only take up to $numResults
        $expectedSummary = "\n- Title: Title A. Snippet: Snippet A.";

        $actualSummary = $this->webSearchTool->search($query, $numResults);
        $this->assertSame($expectedSummary, $actualSummary);
    }

    public function testSearchSuccessfulWithFewerResultsThanRequestedButApiReturnsEvenFewer(): void
    {
        $query = "even less results query";
        $numResults = 3; // Request 3

        $apiResponseContent = "Title: Title Only One\nSnippet: Snippet for Only One."; // API returns only 1
        $mockApiResponse = $this->createMockApiResponse($apiResponseContent);
        
        $this->mockChatResource->expects($this->once())
            ->method('create')
            ->willReturn($mockApiResponse);

        $expectedSummary = "\n- Title: Title Only One. Snippet: Snippet for Only One.";
        $actualSummary = $this->webSearchTool->search($query, $numResults);
        $this->assertSame($expectedSummary, $actualSummary);
    }


    public function testSearchReturnsNoResultsFoundWhenApiReturnsEmptyContent(): void
    {
        $query = "no results query";
        
        // Simulate OpenAI returning no choices or empty content
        $mockApiResponse = $this->createMock(CreateResponse::class);
        $mockApiResponse->choices = []; // No choices
        $mockApiResponse->id = 'chatcmpl-empty';
        $mockApiResponse->object = 'chat.completion';
        $mockApiResponse->created = time();
        $mockApiResponse->model = $this->testOpenAiModel;


        $this->mockChatResource->expects($this->once())
            ->method('create')
            ->willReturn($mockApiResponse);

        $expectedMessage = "No web search results found or unexpected response from AI for: " . htmlspecialchars($query);
        $actualMessage = $this->webSearchTool->search($query);
        $this->assertSame($expectedMessage, $actualMessage);
    }
    
    public function testSearchReturnsCouldNotExtractUsefulInfoFromMalformedContent(): void
    {
        $query = "malformed results query";
        
        $apiResponseContent = "This is not the format you are looking for.";
        $mockApiResponse = $this->createMockApiResponse($apiResponseContent);

        $this->mockChatResource->expects($this->once())
            ->method('create')
            ->willReturn($mockApiResponse);
        
        $expectedMessage = "Could not extract useful information from AI response for: " . htmlspecialchars($query) . ". The AI might not have found relevant information or the format was unexpected.";
        $actualMessage = $this->webSearchTool->search($query);
        $this->assertSame($expectedMessage, $actualMessage);
    }

    public function testSearchHandlesOpenAiApiException(): void
    {
        $query = "openai api exception query";
        $exceptionMessage = "OpenAI API error occurred";

        $this->mockChatResource->expects($this->once())
            ->method('create')
            ->willThrowException(new OpenAIErrorException(['message' => $exceptionMessage, 'type' => 'api_error']));

        $expectedMessage = "Error performing web search: AI service returned an error. " . $exceptionMessage;
        $actualMessage = $this->webSearchTool->search($query);
        $this->assertSame($expectedMessage, $actualMessage);
    }

    public function testSearchHandlesOpenAiTransporterException(): void
    {
        $query = "openai transporter exception query";
        $exceptionMessage = "Network issue with OpenAI";

        $this->mockChatResource->expects($this->once())
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
