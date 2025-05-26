<?php

declare(strict_types=1);

namespace MyApp;

use Google_Service_Customsearch;
use Exception;

class WebSearchTool
{
    private Google_Service_Customsearch $customSearchService;

    /**
     * Constructor.
     *
     * @param Google_Service_Customsearch $customSearchService An initialized Google Custom Search service client.
     */
    public function __construct(Google_Service_Customsearch $customSearchService)
    {
        $this->customSearchService = $customSearchService;
    }

    /**
     * Performs a web search using the injected Google Custom Search API service.
     *
     * @param string $query The search query.
     * @param string $cx The Custom Search Engine ID (CX).
     * @param int $numResults Number of results to fetch.
     * @return string A summary of the search results, or an error message.
     */
    public function search(string $query, string $cx, int $numResults = 3): string
    {
        if (empty($cx)) {
            // CX ID is essential for the call itself.
            return "Error: Custom Search Engine ID (CX) is not provided for web search.";
        }

        try {
            $params = [
                'q' => $query,
                'cx' => $cx,
                'num' => $numResults
            ];

            // $this->customSearchService is already an instance of Google_Service_Customsearch
            $results = $this->customSearchService->cse->listCse($params);
            
            $searchItems = $results->getItems();

            if (empty($searchItems)) {
                return "No web search results found for: " . htmlspecialchars($query);
            }

            $summary = [];
            foreach ($searchItems as $item) {
                $title = $item->getTitle();
                $snippet = $item->getSnippet();
                if (!empty($title) && !empty($snippet)) {
                    $summary[] = rtrim("Title: " . $title, '.') . ". Snippet: " . rtrim($snippet, '.') . ".";
                } elseif (!empty($title)) {
                    $summary[] = "Title: " . rtrim($title, '.') . ".";
                } elseif (!empty($snippet)) {
                    $summary[] = "Snippet: " . rtrim($snippet, '.') . ".";
                }
            }

            if (empty($summary)) {
                return "Could not extract useful information from search results for: " . htmlspecialchars($query);
            }
            
            // Return up to $numResults summaries
            return "Web Search Results:
- " . implode("
- ", array_slice($summary, 0, $numResults));

        } catch (Exception $e) {
            // Log the actual error in a real application
            // error_log("Google Custom Search API error in WebSearchTool: " . $e->getMessage());
            // Provide a more generic message to the user/bot context
            return "Error performing web search. Details: " . $e->getMessage();
        }
    }
}
