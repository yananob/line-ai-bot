<?php

declare(strict_types=1);

namespace MyApp\Tests; // 名前空間を追加

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use MyApp\WebSearchTool;

// OpenAI 関連クラス
use OpenAI\Contracts\ClientContract as OpenAiClientContract;
use OpenAI\Contracts\Resources\ResponsesContract;
use OpenAI\Responses\Responses\CreateResponse as ResponsesCreateResponse; // 実際のレスポンスタイプ用
// 注意: OpenAI\Resources\Chat や OpenAI\Responses\Chat\* は不要になりました

use OpenAI\Exceptions\ErrorException as OpenAIErrorException;
use OpenAI\Exceptions\TransporterException as OpenAITransporterException;
// PHPUnitのTestCaseをuseする (既に追加済みだが、明示的に)
// use PHPUnit\Framework\TestCase; // WebSearchToolTest extends TestCase のため

final class WebSearchToolTest extends TestCase // TestCaseの完全修飾名を使用 (useしたのでこれでOK)
{
    private MockObject $mockOpenAiClient; // OpenAIクライアントのモック
    private MockObject $mockResponsesResource; // OpenAI\Resources\Responses のモック
    private WebSearchTool $webSearchTool; // テスト対象のWebSearchToolインスタンス
    private string $testOpenAiModel = 'gpt-4o'; // responses()->create() をサポートするモデル

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockOpenAiClient = $this->createMock(\OpenAI\Contracts\ClientContract::class);
        $this->mockResponsesResource = $this->createMock(\OpenAI\Contracts\Resources\ResponsesContract::class);

        // モックOpenAiClientがモックResponsesリソースを返すように設定
        $this->mockOpenAiClient->method('responses')->willReturn($this->mockResponsesResource);

        // WebSearchToolをモックOpenAIクライアントとテストモデル名でインスタンス化
        $this->webSearchTool = new WebSearchTool($this->mockOpenAiClient, $this->testOpenAiModel);
    }

    // APIレスポンスのモックを作成するプライベートヘルパーメソッド
    private function createMockApiResponse(array $outputContents): \OpenAI\Responses\Responses\CreateResponse
    {
        $outputMessages = [];
        $messageIdCounter = 0;

        foreach ($outputContents as $contentItemSnippets) {
            $outputTextElements = [];
            if (is_array($contentItemSnippets)) {
                foreach ($contentItemSnippets as $snippetText) {
                    if ($snippetText === 'MALFORMED_CONTENT_ITEM_TYPE') {
                        // このタイプは OutputMessage::from で処理されるが、WebSearchToolの解析ロジックでは無視される
                        $outputTextElements[] = ['type' => 'refusal', 'refusal' => 'シミュレートされた拒否、無視されるべき', 'annotations' => []];
                    } elseif ($snippetText === 'EMPTY_TEXT_CONTENT_ITEM') {
                        $outputTextElements[] = ['type' => 'output_text', 'text' => '', 'annotations' => []];
                    } else {
                        $outputTextElements[] = ['type' => 'output_text', 'text' => $snippetText, 'annotations' => []];
                    }
                }
            } elseif ($contentItemSnippets === 'EMPTY_OUTPUT_ITEM') { // コンテンツがないoutputアイテムの特殊ケース
                 $outputMessages[] = [
                    'type' => 'message',
                    'id' => 'msg_' . (++$messageIdCounter),
                    'role' => 'assistant',
                    'status' => 'completed',
                    'content' => [], // 空のコンテンツ配列
                 ];
                 continue; // 次のoutputContentへ
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
            'reasoning' => null,
            'store' => false,
            'temperature' => null,
            'text' => ['format' => ['type' => 'text', 'text' => ($outputMessages ? '該当する場合のフォーマット済みテキスト' : '')]],
            'tool_choice' => 'none',
            'tools' => [],
            'top_p' => null,
            'truncation' => null,
            'usage' => null,
            'user' => null,
            'metadata' => [],
        ];

        // ダミーのMetaInformationオブジェクトを作成
        $metaData = [
            'x-request-id' => ['req_123'],
            'openai-model' => ['gpt-4o'],
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
        $meta = \OpenAI\Responses\Meta\MetaInformation::from($metaData);

        return \OpenAI\Responses\Responses\CreateResponse::from($attributes, $meta);
    }

    public function test_検索が成功する(): void
    {
        $query = "テストクエリ";
        $numResults = 2;

        // 各内部配列は$outputItemのコンテンツを表し、文字列は個々の'text'フィールド
        $apiOutputContents = [
            ["最初の検索結果スニペット。"], // $outputItem1->content に対応
            ["2番目の検索結果スニペット。"]  // $outputItem2->content に対応
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
        $numResults = 1; // 1件リクエスト

        $apiOutputContents = [
            ["最初のスニペット。"],
            ["2番目のスニペット。"] // APIは2件のスニペットを返す
        ];
        $mockApiResponse = $this->createMockApiResponse($apiOutputContents);

        $this->mockResponsesResource->expects($this->once())
            ->method('create')
            ->willReturn($mockApiResponse);

        $expectedSummary = "\n- Snippet: 最初のスニペット。"; // numResults分だけ取得するべき

        $actualSummary = $this->webSearchTool->search($query, $numResults);
        $this->assertSame($expectedSummary, $actualSummary);
    }

    public function test_リクエストより少ない結果だがAPIはさらに少ない結果を返す場合の検索成功(): void
    {
        $query = "さらに少ない結果クエリ";
        $numResults = 3; // 3件リクエスト

        $apiOutputContents = [
            ["唯一のスニペット。"] // APIは1件のみ返す
        ];
        $mockApiResponse = $this->createMockApiResponse($apiOutputContents);

        $this->mockResponsesResource->expects($this->once())
            ->method('create')
            ->willReturn($mockApiResponse);

        $expectedSummary = "\n- Snippet: 唯一のスニペット。";
        $actualSummary = $this->webSearchTool->search($query, $numResults);
        $this->assertSame($expectedSummary, $actualSummary);
    }

    public function test_検索クエリに最近の結果を求める日本語フレーズが含まれる(): void
    {
        $baseQuery = "元のクエリ";
        $numResults = 1;
        // これが付加されるべき日本語フレーズ（先頭のスペースを含む）
        $expectedPhraseSuffix = " 検索結果はできるだけ新しいものを使うようにしてください。";

        // APIレスポンスをモック
        $apiOutputContents = [["テストスニペット。"]];
        $mockApiResponse = $this->createMockApiResponse($apiOutputContents);

        $this->mockResponsesResource->expects($this->once())
            ->method('create')
            ->with($this->callback(function ($params) use ($baseQuery, $expectedPhraseSuffix) {
                $this->assertArrayHasKey('input', $params, "Params配列には'input'キーが必要です。");
                $this->assertStringEndsWith($expectedPhraseSuffix, $params['input'], "クエリ入力は日本語フレーズで終わるべきです。");
                // ベースクエリが入力の先頭にあるかも確認
                $this->assertStringStartsWith($baseQuery, $params['input'], "クエリ入力はベースクエリで始まるべきです。");
                // 完全なクエリがベースクエリ + サフィックスであることを確認
                $this->assertEquals($baseQuery . $expectedPhraseSuffix, $params['input'], "完全なクエリ文字列が期待通りではありません。");
                return true; // アサーションが通ればコールバックはtrueを返す
            }))
            ->willReturn($mockApiResponse);

        // searchメソッドを呼び出し
        $this->webSearchTool->search($baseQuery, $numResults);
        // このテストではsearch()自体の戻り値を表明する必要はありません。
        // モックされた'create'メソッドに渡された引数に焦点を当てているためです。
    }

    public function test_APIが空の出力配列を返した場合に結果なしメッセージを返す(): void
    {
        $query = "結果なしクエリ空出力";

        $mockApiResponse = $this->createMockApiResponse([]); // 空の出力配列

        $this->mockResponsesResource->expects($this->once())
            ->method('create')
            ->willReturn($mockApiResponse);

        $expectedMessage = "No web search results found or unexpected response structure for: " . htmlspecialchars($query . " 検索結果はできるだけ新しいものを使うようにしてください。");
        $actualMessage = $this->webSearchTool->search($query);
        $this->assertSame($expectedMessage, $actualMessage);
    }

    public function test_APIが出力アイテムに空のコンテンツ配列を返した場合に結果なしメッセージを返す(): void
    {
        $query = "結果なしクエリ空コンテンツ";

        $mockApiResponse = $this->createMockApiResponse([[]]); // 空のコンテンツ配列を持つ出力アイテム

        $this->mockResponsesResource->expects($this->once())
            ->method('create')
            ->willReturn($mockApiResponse);

        // これは実際には「有用な情報を抽出できませんでした...」となる。出力は空ではないが、コンテンツ解析が失敗するため。
        $expectedMessage = "Could not extract useful information from web search results for: " . htmlspecialchars($query . " 検索結果はできるだけ新しいものを使うようにしてください。") . ". The response might not contain suitable text content.";
        $actualMessage = $this->webSearchTool->search($query);
        $this->assertSame($expectedMessage, $actualMessage);
    }


    public function test_不正なコンテントタイプの場合に有用な情報を抽出できなかったメッセージを返す(): void
    {
        $query = "不正コンテントタイプクエリ";

        // createMockApiResponseで不正なコンテントアイテムタイプをトリガーする特殊な文字列を渡す
        $apiOutputContents = [ ['MALFORMED_CONTENT_ITEM_TYPE'] ];
        $mockApiResponse = $this->createMockApiResponse($apiOutputContents);

        $this->mockResponsesResource->expects($this->once())
            ->method('create')
            ->willReturn($mockApiResponse);

        $expectedMessage = "Could not extract useful information from web search results for: " . htmlspecialchars($query . " 検索結果はできるだけ新しいものを使うようにしてください。") . ". The response might not contain suitable text content.";
        $actualMessage = $this->webSearchTool->search($query);
        $this->assertSame($expectedMessage, $actualMessage);
    }

    public function test_コンテントアイテム内のテキストが空の場合に有用な情報を抽出できなかったメッセージを返す(): void
    {
        $query = "空テキストコンテントクエリ";

        $apiOutputContents = [ ['EMPTY_TEXT_CONTENT_ITEM'] ]; // 空テキスト用の特殊文字列
        $mockApiResponse = $this->createMockApiResponse($apiOutputContents);

        $this->mockResponsesResource->expects($this->once())
            ->method('create')
            ->willReturn($mockApiResponse);

        $expectedMessage = "Could not extract useful information from web search results for: " . htmlspecialchars($query . " 検索結果はできるだけ新しいものを使うようにしてください。") . ". The response might not contain suitable text content.";
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
            ->willThrowException(new OpenAIErrorException($errorContents, $statusCode));

        $expectedMessage = "Error performing web search: AI service returned an error. " . $exceptionMessage;
        $actualMessage = $this->webSearchTool->search($query);
        $this->assertSame($expectedMessage, $actualMessage);
    }

    public function test_OpenAI_Transporter例外を処理する(): void
    {
        $query = "openai transporter 例外クエリ";
        $exceptionMessage = "OpenAIとのネットワーク問題 (responses)";

        // Psr\Http\Client\ClientExceptionInterface のスタブを作成
        $clientExceptionStub = new class($exceptionMessage) extends \Exception implements \Psr\Http\Client\ClientExceptionInterface {
            public function __construct(string $message) {
                parent::__construct($message);
            }
        };

        $this->mockResponsesResource->expects($this->once())
            ->method('create')
            ->willThrowException(new OpenAITransporterException($clientExceptionStub));

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

    public function test_結果数が負の場合にエラーを返す(): void
    {
        $this->assertSame(
            "Error: Number of results must be positive.",
            $this->webSearchTool->search("テストクエリ", -1)
        );
    }
}
