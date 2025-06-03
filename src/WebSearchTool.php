<?php

declare(strict_types=1);

namespace MyApp;

use OpenAI\Client;
use OpenAI\Responses\Responses\CreateResponse as ResponsesCreateResponse; // Updated use statement
use OpenAI\Exceptions\ErrorException as OpenAIErrorException;
use OpenAI\Exceptions\TransporterException as OpenAITransporterException;
use Exception; // Keep for general exceptions

class WebSearchTool
{
    private Client $openaiClient;
    private string $openaiModel;

    // Define prompts as class constants to avoid duplication
    private const SYSTEM_PROMPT_TEMPLATE = "You are a simulated web search engine. For the user's query, provide %d relevant findings.
For each finding, strictly format it as:
Title: [The title of the finding]
Snippet: [A brief snippet of the finding]

Separate each finding with exactly two newline characters. Do not include any other text before or after the findings.";

    private const USER_PROMPT_PREFIX = "Search the web for: ";

    /**
     * Constructor.
     *
     * @param Client $openaiClient An initialized OpenAI API client.
     * @param string $openaiModel The OpenAI model to use for searches (e.g., 'gpt-4o' or similar that supports responses()->create).
     */
    public function __construct(Client $openaiClient, string $openaiModel)
    {
        $this->openaiClient = $openaiClient;
        $this->openaiModel = $openaiModel;
    }

    /**
     * Performs a web search using the OpenAI API's responses()->create() with web_search_preview.
     *
     * @param string $query The search query.
     * @param int $numResults Number of desired results/findings. (Note: web_search_preview might not directly support 'numResults' in the API call, so we parse up to this many from the response).
     * @return string A summary of the web search results, or an error message.
     */
    public function search(string $query, int $numResults = 3): string
    {
        if (empty($query)) {
            return "Error: Search query is empty.";
        }
        if ($numResults <= 0) {
            return "Error: Number of results must be positive.";
        }

        // The 'input' for responses()->create should be the user query.
        // The system prompt is not directly used here if web_search_preview provides structured output.
        // If web_search_preview itself needs prompting for format, the approach would differ.
        // For now, we assume web_search_preview returns structured data and the system_prompt below is for LLM text generation, not this tool.

        $params = [
            'model' => $this->openaiModel, // Model used by the main LLM, not necessarily by web_search_preview
            'input' => self::USER_PROMPT_PREFIX . htmlspecialchars($query), // User's query
            'tools' => [['type' => 'web_search_preview']],
            // 'max_output_tokens' might not be directly applicable if 'web_search_preview' dictates output structure.
            // 'temperature' is also more for generative tasks.
            // We are relying on the structure of web_search_preview's output.
        ];

        // The system prompt (like self::SYSTEM_PROMPT_TEMPLATE) would typically be used if we were asking the LLM
        // to generate and format text based on search results, rather than getting structured data from a tool.
        // If `web_search_preview` itself doesn't return structured title/snippet, we might need a different strategy,
        // possibly involving a subsequent LLM call to format raw text from `web_search_preview`.
        // For now, we proceed assuming `web_search_preview` gives structured output.

        try {
            $response = $this->openaiClient->responses()->create($params);
            // Log the raw output part of the OpenAI response for debugging web_search_preview structure
            if (isset($response->output)) {
                error_log("WebSearchTool raw OpenAI response output: " . print_r($response->output, true));
            } else {
                error_log("WebSearchTool raw OpenAI response (full, as output property is missing): " . print_r($response, true));
            }
            return $this->parseAndFormatOpenAIResponse($response, $query, $numResults);

        } catch (OpenAITransporterException $e) {
            error_log("OpenAI API Transporter error in WebSearchTool: " . $e->getMessage());
            return "Error performing web search: Could not connect to the AI service. " . $e->getMessage();
        } catch (OpenAIErrorException $e) {
            error_log("OpenAI API error in WebSearchTool: " . $e->getMessage());
            return "Error performing web search: AI service returned an error. " . $e->getMessage();
        } catch (Exception $e) {
            error_log("Generic error in WebSearchTool: " . $e->getMessage());
            return "Error performing web search. An unexpected error occurred. " . $e->getMessage();
        }
    }

    /**
     * Parses the response from OpenAI's responses()->create() with web_search_preview and formats it.
     * Assumes web_search_preview tool's output is structured within $response->output.
     * Each item in $response->output is expected to be an object or array containing search result details.
     *
     * @param ResponsesCreateResponse $response The response object from OpenAI.
     * @param string $query The original search query (for error messages).
     * @param int $numResults The desired number of results to extract.
     * @return string Formatted summary string or error message.
     */
    private function parseAndFormatOpenAIResponse(ResponsesCreateResponse $response, string $query, int $numResults): string
    {
        $findings = [];

        // Check if $response->output exists and is an array
        if (!isset($response->output) || !is_array($response->output)) {
            // Log the actual response structure for debugging
            // error_log("WebSearchTool: Unexpected response structure for query '{$query}'. Response: " . print_r($response, true));
            return "No web search results found or unexpected response structure for: " . htmlspecialchars($query);
        }

        // Iterate through the output items, assuming they are the search results
        foreach ($response->output as $resultItem) {
            if (count($findings) >= $numResults) {
                break;
            }

            // Assuming each $resultItem is an object with 'title' and 'snippet' properties.
            // This is an educated guess on the structure of web_search_preview's output.
            // The actual properties might be different (e.g., 'name', 'description', 'url', 'content').
            // If $resultItem is an array, access would be $resultItem['title'], $resultItem['snippet'].

            $title = null;
            $snippet = null;

            // Attempt to extract title and snippet
            // This part is speculative and depends on the actual structure of $resultItem
            if (is_object($resultItem)) {
                // Option 1: Direct properties (common for search results)
                if (isset($resultItem->title) && is_string($resultItem->title)) {
                    $title = trim($resultItem->title);
                }
                if (isset($resultItem->snippet) && is_string($resultItem->snippet)) {
                    $snippet = trim($resultItem->snippet);
                } elseif (isset($resultItem->description) && is_string($resultItem->description)) { // Fallback for snippet
                    $snippet = trim($resultItem->description);
                } elseif (isset($resultItem->text) && is_string($resultItem->text)) { // Fallback for snippet
                    $snippet = trim($resultItem->text);
                }

                // Option 2: Nested under a 'content' or 'data' property (less likely for direct tool output but possible)
                // else if (isset($resultItem->content) && is_object($resultItem->content)) { ... }

            } elseif (is_array($resultItem)) {
                // Similar checks if $resultItem is an associative array
                if (isset($resultItem['title']) && is_string($resultItem['title'])) {
                    $title = trim($resultItem['title']);
                }
                if (isset($resultItem['snippet']) && is_string($resultItem['snippet'])) {
                    $snippet = trim($resultItem['snippet']);
                } elseif (isset($resultItem['description']) && is_string($resultItem['description'])) {
                    $snippet = trim($resultItem['description']);
                } elseif (isset($resultItem['text']) && is_string($resultItem['text'])) {
                    $snippet = trim($resultItem['text']);
                }
            }


            if ($title && $snippet) {
                $findings[] = "Title: " . $title . "\nSnippet: " . rtrim($snippet, '.') . ".";
            } elseif ($snippet) { // If only snippet is found (fallback)
                $findings[] = "Snippet: " . rtrim($snippet, '.') . ".";
            }
            // If only title is found, we might choose to discard it or format differently.
            // For now, we require at least a snippet.
        }

        if (empty($findings)) {
             // error_log("WebSearchTool: Could not extract title/snippet from response for query '{$query}'. Response output: " . print_r($response->output, true));
            return "Could not extract useful information from web search results for: " . htmlspecialchars($query) . ". The response might not contain suitable structured content.";
        }

        return "\n- " . implode("\n\n- ", $findings); // Separate findings with double newlines
    }
}
