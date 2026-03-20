<?php

declare(strict_types=1);

namespace App\Infrastructure\Search;

use App\Domain\Bot\Service\WebSearchInterface;
use OpenAI\Contracts\ClientContract;
use OpenAI\Responses\Responses\CreateResponse as ResponsesCreateResponse;
use OpenAI\Exceptions\ErrorException as OpenAIErrorException;
use OpenAI\Exceptions\TransporterException as OpenAITransporterException;
use Exception;

class OpenAIWebSearchTool implements WebSearchInterface
{
    private ClientContract $openaiClient;
    private string $openaiModel;

    /**
     * Constructor.
     *
     * @param ClientContract $openaiClient An initialized OpenAI API client.
     * @param string $openaiModel The OpenAI model to use for searches.
     */
    public function __construct(ClientContract $openaiClient, string $openaiModel)
    {
        $this->openaiClient = $openaiClient;
        $this->openaiModel = $openaiModel;
    }

    /**
     * Performs a web search using the OpenAI API's responses()->create() with web_search_preview.
     *
     * @param string $query The search query.
     * @param int $numResults Number of desired results/findings.
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

        // Modify the query to prefer recent information
        $query .= " 検索結果はできるだけ新しいものを使うようにしてください。";

        $params = [
            'model' => $this->openaiModel,
            'input' => $query,
            'tools' => [['type' => 'web_search_preview']],
            'max_output_tokens' => 200 * $numResults,
            'temperature' => 0.7,
        ];

        try {
            $response = $this->openaiClient->responses()->create($params);
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
    }

    /**
     * Parses the response from OpenAI's responses()->create() with web_search_preview and formats it.
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
                        break 2;
                    }
                    if (isset($contentItem->type) && $contentItem->type === 'output_text' && !empty($contentItem->text)) {
                        $findings[] = "Snippet: " . rtrim(trim($contentItem->text), '.');
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
