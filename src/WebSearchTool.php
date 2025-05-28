<?php

declare(strict_types=1);

namespace MyApp;

use OpenAI;
use OpenAI\Client;
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
     * @param string $openaiModel The OpenAI model to use for searches (e.g., 'gpt-3.5-turbo').
     */
    public function __construct(Client $openaiClient, string $openaiModel)
    {
        $this->openaiClient = $openaiClient;
        $this->openaiModel = $openaiModel;
    }

    /**
     * Performs a simulated web search using the OpenAI API.
     *
     * @param string $query The search query.
     * @param int $numResults Number of desired results/findings.
     * @return string A summary of the simulated search results, or an error message.
     */
    public function search(string $query, int $numResults = 3): string
    {
        if (empty($query)) {
            return "Error: Search query is empty.";
        }
        if ($numResults <= 0) {
            return "Error: Number of results must be positive.";
        }

        // System prompt to guide the AI's response format
        $systemPrompt = "You are a simulated web search engine. For the user's query, provide {$numResults} relevant findings.
For each finding, strictly format it as:
Title: [The title of the finding]
Snippet: [A brief snippet of the finding]

Separate each finding with exactly two newline characters. Do not include any other text before or after the findings.";

        // User prompt with the actual query
        $userPrompt = "Search the web for: " . htmlspecialchars($query);

        try {
            $response = $this->openaiClient->chat()->completions()->create([
                'model' => $this->openaiModel,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'max_tokens' => 100 * $numResults, // Estimate tokens needed, adjust as necessary
                'temperature' => 0.5, // Adjust for creativity vs factualness
            ]);

            if (empty($response->choices[0]->message->content)) {
                return "No web search results found or unexpected response from AI for: " . htmlspecialchars($query);
            }

            $rawContent = $response->choices[0]->message->content;
            return $this->parseAndFormatOpenAIResponse($rawContent, $query, $numResults);

        } catch (OpenAITransporterException $e) {
            // Specific error for network/transport issues with OpenAI
            error_log("OpenAI API Transporter error in WebSearchTool: " . $e->getMessage());
            return "Error performing web search: Could not connect to the AI service. " . $e->getMessage();
        } catch (OpenAIErrorException $e) {
            // Specific error for API errors from OpenAI (e.g., auth, rate limits)
            error_log("OpenAI API error in WebSearchTool: " . $e->getMessage());
            return "Error performing web search: AI service returned an error. " . $e->getMessage();
        } catch (Exception $e) {
            // General catch-all for other unexpected issues
            error_log("Generic error in WebSearchTool: " . $e->getMessage());
            return "Error performing web search. An unexpected error occurred. " . $e->getMessage();
        }
    }

    /**
     * Parses the raw text response from OpenAI and formats it.
     *
     * @param string $rawContent The raw text content from OpenAI.
     * @param string $query The original search query (for error messages).
     * @param int $numResults The expected number of results.
     * @return string Formatted summary string or error message.
     */
    private function parseAndFormatOpenAIResponse(string $rawContent, string $query, int $numResults): string
    {
        $findings = [];
        $results = explode("\n\n", trim($rawContent)); // Split by two newlines

        foreach ($results as $result) {
            if (count($findings) >= $numResults) {
                break;
            }
            $title = null;
            $snippet = null;

            $lines = explode("\n", $result);
            foreach ($lines as $line) {
                if (strpos($line, 'Title: ') === 0) {
                    $title = substr($line, strlen('Title: '));
                } elseif (strpos($line, 'Snippet: ') === 0) {
                    $snippet = substr($line, strlen('Snippet: '));
                }
            }

            if ($title && $snippet) {
                $findings[] = "Title: " . rtrim($title, '.') . ". Snippet: " . rtrim($snippet, '.') . ".";
            } elseif ($title) { // Fallback if only title is found
                $findings[] = "Title: " . rtrim($title, '.') . ".";
            } elseif ($snippet) { // Fallback if only snippet is found
                $findings[] = "Snippet: " . rtrim($snippet, '.') . ".";
            }
        }

        if (empty($findings)) {
            // Log $rawContent here for debugging if needed
            // error_log("Could not parse any findings from OpenAI response for query '{$query}'. Raw: " . $rawContent);
            return "Could not extract useful information from AI response for: " . htmlspecialchars($query) . ". The AI might not have found relevant information or the format was unexpected.";
        }

        return "\n- " . implode("\n- ", $findings);
    }
}
