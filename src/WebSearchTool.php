<?php

declare(strict_types=1);

namespace MyApp;

// Add these if not present (assuming google/apiclient is available)
use Google_Client;
use Google_Service_Customsearch;
use Exception; // For error handling

class WebSearchTool
{
    /**
     * Performs a web search using Google Custom Search API.
     *
     * @param string $query The search query.
     * @param string $apiKey The Google API Key.
     * @param string $cx The Custom Search Engine ID (CX).
     * @param int $numResults Number of results to fetch (default 3).
     * @return string A summary of the search results, or an error message.
     */
    public static function search(string $query, string $apiKey, string $cx, int $numResults = 3): string
    {
        if (empty($apiKey) || empty($cx)) {
            return "Error: API key or CX ID is not configured for web search.";
        }

        try {
            $client = new Google_Client();
            $client->setDeveloperKey($apiKey);
            $service = new Google_Service_Customsearch($client);

            $params = [
                'q' => $query,
                'cx' => $cx,
                'num' => $numResults
            ];

            $results = $service->cse->listCse($params);
            
            $searchItems = $results->getItems();

            if (empty($searchItems)) {
                return "No web search results found for: " . $query;
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
                return "Could not extract useful information from search results for: " . $query;
            }
            
            return "Web Search Results:
- " . implode("
- ", array_slice($summary, 0, $numResults));

        } catch (Exception $e) {
            // Log the actual error in a real application
            // error_log("Google Custom Search API error: " . $e->getMessage());
            return "Error performing web search: " . $e->getMessage();
        }
    }
}
