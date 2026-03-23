<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Search;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use App\Infrastructure\Search\OpenAIWebSearchTool;
use OpenAI\Contracts\ClientContract;
use OpenAI\Contracts\Resources\ResponsesContract;
use OpenAI\Responses\Responses\CreateResponse;
use OpenAI\Responses\Meta\MetaInformation;
use OpenAI\Exceptions\ErrorException;
use OpenAI\Exceptions\TransporterException;

final class OpenAIWebSearchToolTest extends TestCase
{
    private MockObject $mockOpenAiClient;
    private MockObject $mockResponsesResource;
    private OpenAIWebSearchTool $webSearchTool;
    private string $testOpenAiModel = 'gpt-5-mini';

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockOpenAiClient = $this->createMock(ClientContract::class);
        $this->mockResponsesResource = $this->createMock(ResponsesContract::class);

        $this->mockOpenAiClient->method('responses')->willReturn($this->mockResponsesResource);

        $this->webSearchTool = new OpenAIWebSearchTool($this->mockOpenAiClient, $this->testOpenAiModel);
    }

    private function createMockApiResponse(array $outputContents): CreateResponse
    {
        $outputMessages = [];
        $messageIdCounter = 0;

        foreach ($outputContents as $contentItemSnippets) {
            $outputTextElements = [];
            if (is_array($contentItemSnippets)) {
                foreach ($contentItemSnippets as $snippetText) {
                    if ($snippetText === 'MALFORMED_CONTENT_ITEM_TYPE') {
                        $outputTextElements[] = ['type' => 'refusal', 'refusal' => 'simulated refusal', 'annotations' => []];
                    } elseif ($snippetText === 'EMPTY_TEXT_CONTENT_ITEM') {
                        $outputTextElements[] = ['type' => 'output_text', 'text' => '', 'annotations' => []];
                    } else {
                        $outputTextElements[] = ['type' => 'output_text', 'text' => $snippetText, 'annotations' => []];
                    }
                }
            } elseif ($contentItemSnippets === 'EMPTY_OUTPUT_ITEM') {
                 $outputMessages[] = [
                    'type' => 'message',
                    'id' => 'msg_' . (++$messageIdCounter),
                    'role' => 'assistant',
                    'status' => 'completed',
                    'content' => [],
                 ];
                 continue;
            }

            $outputMessages[] = [
                'type' => 'message',
                'id' => 'msg_' . (++$messageIdCounter),
                'role' => 'assistant',
                'status' => 'completed',
                'content' => $outputTextElements,
            ];
        }

        $attributes = [
            'id' => 'resp-test123',
            'object' => 'response',
            'created_at' => time(),
            'status' => 'completed',
            'error' => null,
            'incomplete_details' => null,
            'instructions' => null,
            'max_output_tokens' => null,
            'model' => $this->testOpenAiModel,
            'output' => $outputMessages,
            'parallel_tool_calls' => false,
            'previous_response_id' => null,
            'reasoning' => ['effort' => 'none', 'generate_summary' => null],
            'store' => false,
            'temperature' => null,
            'text' => ['format' => ['type' => 'text', 'text' => ($outputMessages ? 'formatted text' : '')]],
            'tool_choice' => 'none',
            'tools' => [],
            'top_p' => null,
            'truncation' => null,
            'usage' => null,
            'user' => null,
            'metadata' => [],
        ];

        /** @var array{x-request-id: string[], openai-model: string[], openai-organization: string[], openai-version: string[], openai-processing-ms: string[], x-ratelimit-limit-requests: string[], x-ratelimit-remaining-requests: string[], x-ratelimit-reset-requests: string[], x-ratelimit-limit-tokens: string[], x-ratelimit-remaining-tokens: string[], x-ratelimit-reset-tokens: string[]} $headers */
        $headers = [
            'x-request-id' => ['req_123'],
            'openai-model' => ['gpt-5-mini'],
            'openai-organization' => ['org-123'],
            'openai-version' => ['2020-10-01'],
            'openai-processing-ms' => ['100'],
            'x-ratelimit-limit-requests' => ['100'],
            'x-ratelimit-remaining-requests' => ['99'],
            'x-ratelimit-reset-requests' => ['1s'],
            'x-ratelimit-limit-tokens' => ['1000'],
            'x-ratelimit-remaining-tokens' => ['999'],
            'x-ratelimit-reset-tokens' => ['1s'],
        ];
        $meta = MetaInformation::from($headers);

        return CreateResponse::from($attributes, $meta);
    }

    public function test_検索が成功する(): void
    {
        $query = "テストクエリ";
        $numResults = 2;

        $apiOutputContents = [
            ["最初の検索結果スニペット。"],
            ["2番目の検索結果スニペット。"]
        ];
        $mockApiResponse = $this->createMockApiResponse($apiOutputContents);

        $this->mockResponsesResource->expects($this->once())
            ->method('create')
            ->willReturn($mockApiResponse);

        $expectedSummary = "\n- Snippet: 最初の検索結果スニペット。\n- Snippet: 2番目の検索結果スニペット。";

        $actualSummary = $this->webSearchTool->search($query, $numResults);
        $this->assertSame($expectedSummary, $actualSummary);
    }

    public function test_1つの出力アイテムに複数のテキストが含まれる場合の検索成功(): void
    {
        $query = "複数テキストクエリ";
        $numResults = 2;

        $apiOutputContents = [
            ["最初のスニペット。", "同じ出力アイテムからの2番目のスニペット。"]
        ];
        $mockApiResponse = $this->createMockApiResponse($apiOutputContents);

        $this->mockResponsesResource->expects($this->once())
            ->method('create')
            ->willReturn($mockApiResponse);

        $expectedSummary = "\n- Snippet: 最初のスニペット。\n- Snippet: 同じ出力アイテムからの2番目のスニペット。";

        $actualSummary = $this->webSearchTool->search($query, $numResults);
        $this->assertSame($expectedSummary, $actualSummary);
    }

    public function test_APIから返された結果より少ない結果数での検索成功(): void
    {
        $query = "少ない結果クエリ";
        $numResults = 1;

        $apiOutputContents = [
            ["最初のスニペット。"],
            ["2番目のスニペット。"]
        ];
        $mockApiResponse = $this->createMockApiResponse($apiOutputContents);

        $this->mockResponsesResource->expects($this->once())
            ->method('create')
            ->willReturn($mockApiResponse);

        $expectedSummary = "\n- Snippet: 最初のスニペット。";

        $actualSummary = $this->webSearchTool->search($query, $numResults);
        $this->assertSame($expectedSummary, $actualSummary);
    }

    public function test_検索クエリに最近の結果を求める日本語フレーズが含まれる(): void
    {
        $baseQuery = "元のクエリ";
        $numResults = 1;
        $expectedPhraseSuffix = " 検索結果はできるだけ新しいものを使うようにしてください。";

        $apiOutputContents = [["テストスニペット。"]];
        $mockApiResponse = $this->createMockApiResponse($apiOutputContents);

        $this->mockResponsesResource->expects($this->once())
            ->method('create')
            ->with($this->callback(function ($params) use ($baseQuery, $expectedPhraseSuffix) {
                return $params['input'] === $baseQuery . $expectedPhraseSuffix;
            }))
            ->willReturn($mockApiResponse);

        $this->webSearchTool->search($baseQuery, $numResults);
    }

    public function test_APIが空の出力配列を返した場合に結果なしメッセージを返す(): void
    {
        $query = "結果なしクエリ空出力";

        $mockApiResponse = $this->createMockApiResponse([]);

        $this->mockResponsesResource->expects($this->once())
            ->method('create')
            ->willReturn($mockApiResponse);

        $expectedMessage = "No web search results found or unexpected response structure for: " . htmlspecialchars($query . " 検索結果はできるだけ新しいものを使うようにしてください。");
        $actualMessage = $this->webSearchTool->search($query);
        $this->assertSame($expectedMessage, $actualMessage);
    }

    public function test_OpenAI_API例外を処理する(): void
    {
        $query = "openai api 例外クエリ";
        $exceptionMessage = "OpenAI APIエラーが発生しました (responses)";
        $errorCode = "test_error_code";
        $errorType = "api_error";
        $statusCode = 500;

        $errorContents = [
            'message' => $exceptionMessage,
            'type' => $errorType,
            'code' => $errorCode,
        ];

        $this->mockResponsesResource->expects($this->once())
            ->method('create')
            ->willThrowException(new ErrorException($errorContents, $statusCode));

        $expectedMessage = "Error performing web search: AI service returned an error. " . $exceptionMessage;
        $actualMessage = $this->webSearchTool->search($query);
        $this->assertSame($expectedMessage, $actualMessage);
    }

    public function test_OpenAI_Transporter例外を処理する(): void
    {
        $query = "openai transporter 例外クエリ";
        $exceptionMessage = "OpenAIとのネットワーク問題 (responses)";

        $clientExceptionStub = new class($exceptionMessage) extends \Exception implements \Psr\Http\Client\ClientExceptionInterface {
            public function __construct(string $message) {
                parent::__construct($message);
            }
        };

        $this->mockResponsesResource->expects($this->once())
            ->method('create')
            ->willThrowException(new TransporterException($clientExceptionStub));

        $expectedMessage = "Error performing web search: Could not connect to the AI service. " . $exceptionMessage;
        $actualMessage = $this->webSearchTool->search($query);
        $this->assertSame($expectedMessage, $actualMessage);
    }

    public function test_空クエリの場合にエラーを返す(): void
    {
        $this->assertSame(
            "Error: Search query is empty.",
            $this->webSearchTool->search("")
        );
    }

    public function test_結果数がゼロの場合にエラーを返す(): void
    {
        $this->assertSame(
            "Error: Number of results must be positive.",
            $this->webSearchTool->search("テストクエリ", 0)
        );
    }
}
