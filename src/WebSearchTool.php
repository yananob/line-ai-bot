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

        $params = [
            'model' => $this->openaiModel,
            'input' => $query,
            'tools' => [['type' => 'web_search_preview']],
            'max_output_tokens' => 200 * $numResults, // Estimate, actual output structure will dictate usefulness
            'temperature' => 0.7,
        ];

        // System prompt to guide the AI's response format
        $systemPrompt = "You are a simulated web search engine. For the user's query, provide {$numResults} relevant findings.
For each finding, strictly format it as:
Title: [The title of the finding]
Snippet: [A brief snippet of the finding]

Separate each finding with exactly two newline characters. Do not include any other text before or after the findings.";

        // User prompt with the actual query
        $userPrompt = "Search the web for: " . htmlspecialchars($query);

        // System prompt to guide the AI's response format
        $systemPrompt = "You are a simulated web search engine. For the user's query, provide {$numResults} relevant findings.
For each finding, strictly format it as:
Title: [The title of the finding]
Snippet: [A brief snippet of the finding]

Separate each finding with exactly two newline characters. Do not include any other text before or after the findings.";

        // User prompt with the actual query
        $userPrompt = "Search the web for: " . htmlspecialchars($query);

        // System prompt to guide the AI's response format
        $systemPrompt = "You are a simulated web search engine. For the user's query, provide {$numResults} relevant findings.
For each finding, strictly format it as:
Title: [The title of the finding]
Snippet: [A brief snippet of the finding]

Separate each finding with exactly two newline characters. Do not include any other text before or after the findings.";

        // User prompt with the actual query
        $userPrompt = "Search the web for: " . htmlspecialchars($query);

        try {
            // Note: The actual method might be slightly different based on client version for experimental features
            // Assuming $this->openaiClient->responses()->create(...) is the correct path.
            $response = $this->openaiClient->responses()->create($params);

            // Log the raw response object
            error_log(print_r($response, true));

            // The response structure for web_search_preview needs to be handled by parseAndFormatOpenAIResponse
            return $this->parseAndFormatOpenAIResponse($response, $query, $numResults);

        } catch (OpenAITransporterException $e) {
            error_log("OpenAI API Transporter error in WebSearchTool (responses): " . $e->getMessage());
            return "Error performing web search: Could not connect to the AI service. " . $e->getMessage();
        } catch (OpenAIErrorException $e) {
            error_log("OpenAI API error in WebSearchTool (responses): " . $e->getMessage());
            return "Error performing web search: AI service returned an error. " . $e->getMessage();
        } catch (Exception $e) {
            error_log("Generic error in WebSearchTool (responses): " . $e->getMessage());
            return "Error performing web search. An unexpected error occurred. " . $e->getMessage();
        }

        if (empty($findings)) {
            // Log $rawContent here for debugging if needed
            // error_log("Could not parse any findings from OpenAI response for query '{$query}'. Raw: " . $rawContent);
            return "Could not extract useful information from AI response for: " . htmlspecialchars($query) . ". The AI might not have found relevant information or the format was unexpected.";
        }

        return "\n- " . implode("\n- ", $findings);
    }

    /**
     * Parses the response from OpenAI's responses()->create() with web_search_preview and formats it.
     *
     * @param ResponsesCreateResponse $response The response object from OpenAI.
     * @param string $query The original search query (for error messages).
     * @param int $numResults The desired number of results to extract.
     * @return string Formatted summary string or error message.
     */
    private function parseAndFormatOpenAIResponse(ResponsesCreateResponse $response, string $query, int $numResults): string
    {
        $findings = [];

        if (empty($response->output)) {
            return "No web search results found or unexpected response structure for: " . htmlspecialchars($query);
        }

        foreach ($response->output as $outputItem) {
            if (count($findings) >= $numResults) {
                break;
            }
            if (isset($outputItem->content) && is_array($outputItem->content)) {
                foreach ($outputItem->content as $contentItem) {
                    if (count($findings) >= $numResults) {
                        break 2; // Break outer loop as well
                    }
                    // Assuming $contentItem is an object with 'type' and 'text' properties
                    if (isset($contentItem->type) && $contentItem->type === 'output_text' && !empty($contentItem->text)) {
                        $findings[] = "Snippet: " . rtrim(trim($contentItem->text), '.') . ".";
                    }
                }
            }
        }

        if (empty($findings)) {
            return "Could not extract useful information from web search results for: " . htmlspecialchars($query) . ". The response might not contain suitable text content.";
        }

        return "\n- " . implode("\n- ", $findings);
    }
}
