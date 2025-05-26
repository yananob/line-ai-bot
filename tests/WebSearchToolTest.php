<?php

declare(strict_types=1);

namespace MyApp\Tests;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use MyApp\WebSearchTool;
use Google_Service_Customsearch;
use Google_Service_Customsearch_Resource_Cse; // Resource class for cse->listCse
use Google_Service_Customsearch_Search;       // Return type of listCse method
use Google_Service_Customsearch_Result;       // Type for individual search items
use Exception; // For testing exception handling

class WebSearchToolTest extends TestCase
{
    private MockObject $mockCustomSearchService;
    private MockObject $mockCseResource;
    private WebSearchTool $webSearchTool;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock for Google_Service_Customsearch_Resource_Cse
        $this->mockCseResource = $this->createMock(Google_Service_Customsearch_Resource_Cse::class);

        // Mock for Google_Service_Customsearch
        $this->mockCustomSearchService = $this->createMock(Google_Service_Customsearch::class);
        // The 'cse' property is public. We can assign our mock resource to it.
        $this->mockCustomSearchService->cse = $this->mockCseResource;

        // Instantiate WebSearchTool with the mocked service
        $this->webSearchTool = new WebSearchTool($this->mockCustomSearchService);
    }

    public function testSearchReturnsErrorIfCxIsMissing(): void
    {
        $this->assertSame(
            "Error: Custom Search Engine ID (CX) is not provided for web search.",
            $this->webSearchTool->search("test query", "") // CX is ""
        );
    }

    public function testSearchSuccessful(): void
    {
        $query = "test query";
        $cx = "test_cx";
        $numResults = 2;

        // Prepare mock search result items
        $item1 = $this->createMock(Google_Service_Customsearch_Result::class);
        $item1->method('getTitle')->willReturn("Title 1");
        $item1->method('getSnippet')->willReturn("Snippet for item 1.");

        $item2 = $this->createMock(Google_Service_Customsearch_Result::class);
        $item2->method('getTitle')->willReturn("Title 2");
        $item2->method('getSnippet')->willReturn("Snippet for item 2"); // No trailing period

        $item3 = $this->createMock(Google_Service_Customsearch_Result::class);
        $item3->method('getTitle')->willReturn("Title 3 Only"); // No snippet
        $item3->method('getSnippet')->willReturn(null);


        $mockSearchResults = $this->createMock(Google_Service_Customsearch_Search::class);
        $mockSearchResults->method('getItems')->willReturn([$item1, $item2, $item3]);

        $this->mockCseResource->expects($this->once())
            ->method('listCse')
            ->with(['q' => $query, 'cx' => $cx, 'num' => $numResults])
            ->willReturn($mockSearchResults);

        $expectedSummary = "Web Search Results:
"
                         . "- Title: Title 1. Snippet: Snippet for item 1.
"
                         . "- Title: Title 2. Snippet: Snippet for item 2.";
                         // Item 3 should be sliced off by numResults = 2 if array_slice is used on summary,
                         // or because we only request 2 items. The code gets $numResults items.
                         // The code gets all items then array_slice. So item3 will be in $summary then sliced.

        $actualSummary = $this->webSearchTool->search($query, $cx, $numResults);
        $this->assertSame($expectedSummary, $actualSummary);
    }
    
    public function testSearchSuccessfulWithFewerResultsThanRequested(): void
    {
        $query = "less results query";
        $cx = "test_cx_less";
        $numResults = 3; // Request 3

        $item1 = $this->createMock(Google_Service_Customsearch_Result::class);
        $item1->method('getTitle')->willReturn("Title A");
        $item1->method('getSnippet')->willReturn("Snippet A.");

        $mockSearchResults = $this->createMock(Google_Service_Customsearch_Search::class);
        $mockSearchResults->method('getItems')->willReturn([$item1]); // API returns only 1

        $this->mockCseResource->expects($this->once())
            ->method('listCse')
            ->with(['q' => $query, 'cx' => $cx, 'num' => $numResults])
            ->willReturn($mockSearchResults);

        $expectedSummary = "Web Search Results:
"
                         . "- Title: Title A. Snippet: Snippet A.";

        $actualSummary = $this->webSearchTool->search($query, $cx, $numResults);
        $this->assertSame($expectedSummary, $actualSummary);
    }


    public function testSearchReturnsNoResultsFound(): void
    {
        $query = "no results query";
        $cx = "test_cx_no_results";

        $mockSearchResults = $this->createMock(Google_Service_Customsearch_Search::class);
        $mockSearchResults->method('getItems')->willReturn([]); // Empty array of items

        $this->mockCseResource->expects($this->once())
            ->method('listCse')
            ->willReturn($mockSearchResults);

        $expectedMessage = "No web search results found for: " . htmlspecialchars($query);
        $actualMessage = $this->webSearchTool->search($query, $cx);
        $this->assertSame($expectedMessage, $actualMessage);
    }

    public function testSearchReturnsCouldNotExtractUsefulInfo(): void
    {
        $query = "empty items query";
        $cx = "test_cx_empty_items";

        // Prepare mock search result items with no title or snippet
        $item1 = $this->createMock(Google_Service_Customsearch_Result::class);
        $item1->method('getTitle')->willReturn(null);
        $item1->method('getSnippet')->willReturn(null);

        $mockSearchResults = $this->createMock(Google_Service_Customsearch_Search::class);
        $mockSearchResults->method('getItems')->willReturn([$item1]);

        $this->mockCseResource->expects($this->once())
            ->method('listCse')
            ->willReturn($mockSearchResults);
        
        $expectedMessage = "Could not extract useful information from search results for: " . htmlspecialchars($query);
        $actualMessage = $this->webSearchTool->search($query, $cx);
        $this->assertSame($expectedMessage, $actualMessage);
    }

    public function testSearchHandlesApiException(): void
    {
        $query = "api exception query";
        $cx = "test_cx_exception";
        $exceptionMessage = "API communication failed";

        $this->mockCseResource->expects($this->once())
            ->method('listCse')
            ->willThrowException(new Exception($exceptionMessage));

        $expectedMessage = "Error performing web search. Details: " . $exceptionMessage;
        $actualMessage = $this->webSearchTool->search($query, $cx);
        $this->assertSame($expectedMessage, $actualMessage);
    }
}
