<?php

declare(strict_types=1);

namespace Tests\Domain\Bot\Service;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use App\Domain\Bot\Service\WebSearchDomainService;
use App\Domain\Bot\Service\GptInterface;
use App\Domain\Bot\Service\WebSearchInterface;
use App\Domain\Bot\Messages;
use Carbon\Carbon;

final class WebSearchDomainServiceTest extends TestCase
{
    private GptInterface&MockObject $gptMock;
    private WebSearchInterface&MockObject $webSearchMock;
    private WebSearchDomainService $webSearchDomainService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gptMock = $this->createMock(GptInterface::class);
        $this->webSearchMock = $this->createMock(WebSearchInterface::class);
        $this->webSearchDomainService = new WebSearchDomainService($this->gptMock, $this->webSearchMock);
    }

    public function test_検索が不要な場合はnullを返す(): void
    {
        $this->gptMock->expects($this->once())
            ->method('getAnswer')
            ->with(
                $this->equalTo(Messages::PROMPT_JUDGE_WEB_SEARCH),
                $this->equalTo("こんにちは")
            )
            ->willReturn("いいえ");

        $this->webSearchMock->expects($this->never())->method('search');

        $result = $this->webSearchDomainService->performWebSearchIfNeeded("こんにちは");
        $this->assertNull($result);
    }

    public function test_検索が必要な場合は最適化されたクエリを生成して検索を実行する(): void
    {
        // 固定の現在日時を設定
        Carbon::setTestNow(Carbon::create(2025, 1, 21, 12, 0, 0, 'Asia/Tokyo'));

        $this->gptMock->expects($this->exactly(2))
            ->method('getAnswer')
            ->willReturnCallback(function (string $context, string $message) {
                if ($context === Messages::PROMPT_JUDGE_WEB_SEARCH) {
                    return "はい";
                }

                // 最適化クエリプロンプトに現在時刻が埋め込まれていることを検証
                if (str_contains($context, Messages::PROMPT_GENERATE_WEB_SEARCH_QUERY) || str_contains($context, "2025年01月21日")) {
                    return "東京 天気 2025年01月21日";
                }

                return "";
            });

        $this->webSearchMock->expects($this->once())
            ->method('search')
            ->with(
                $this->equalTo("東京 天気 2025年01月21日"),
                $this->equalTo(5)
            )
            ->willReturn("晴れ、最高気温10度");

        $result = $this->webSearchDomainService->performWebSearchIfNeeded("今日の東京の天気を教えて");
        $this->assertSame("晴れ、最高気温10度", $result);

        Carbon::setTestNow(); // テスト用の現在日時をリセット
    }

    public function test_最適化されたクエリが空の場合は元のメッセージを使用する(): void
    {
        Carbon::setTestNow(Carbon::create(2025, 1, 21, 12, 0, 0, 'Asia/Tokyo'));

        $this->gptMock->expects($this->exactly(2))
            ->method('getAnswer')
            ->willReturnCallback(function (string $context, string $message) {
                if ($context === Messages::PROMPT_JUDGE_WEB_SEARCH) {
                    return "はい";
                }
                // 空レスポンス
                return "";
            });

        $this->webSearchMock->expects($this->once())
            ->method('search')
            ->with(
                $this->equalTo("今日の東京の天気を教えて"),
                $this->equalTo(5)
            )
            ->willReturn("晴れ、最高気温10度");

        $result = $this->webSearchDomainService->performWebSearchIfNeeded("今日の東京の天気を教えて");
        $this->assertSame("晴れ、最高気温10度", $result);

        Carbon::setTestNow();
    }

    public function test_検索ツールが未設定の場合はエラーメッセージを返す(): void
    {
        $domainServiceWithoutTool = new WebSearchDomainService($this->gptMock, null);

        $this->gptMock->expects($this->once())
            ->method('getAnswer')
            ->with(Messages::PROMPT_JUDGE_WEB_SEARCH)
            ->willReturn("はい");

        $result = $domainServiceWithoutTool->performWebSearchIfNeeded("今日の東京の天気を教えて");
        $this->assertSame("Error: Web search tool is not configured properly or failed to initialize.", $result);
    }
}
