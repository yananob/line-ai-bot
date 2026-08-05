<?php

declare(strict_types=1);

namespace App\Domain\Bot\Service;

use App\Domain\Bot\Messages;

class WebSearchDomainService
{
    private GptInterface $gpt;
    private ?WebSearchInterface $webSearchTool;

    public function __construct(GptInterface $gpt, ?WebSearchInterface $webSearchTool = null)
    {
        $this->gpt = $gpt;
        $this->webSearchTool = $webSearchTool;
    }

    public function performWebSearchIfNeeded(string $messageContent): ?string
    {
        if (!$this->shouldPerformWebSearch($messageContent)) {
            return null;
        }

        if ($this->webSearchTool instanceof WebSearchInterface) {
            $optimizedQuery = $this->generateOptimizedQuery($messageContent);
            return $this->webSearchTool->search($optimizedQuery, 5);
        }

        return "Error: Web search tool is not configured properly or failed to initialize.";
    }

    private function shouldPerformWebSearch(string $messageContent): bool
    {
        $response = trim($this->gpt->getAnswer(
            context: Messages::PROMPT_JUDGE_WEB_SEARCH,
            message: $messageContent,
        ));
        return $response === "はい";
    }

    /**
     * GPTを使って現在の日時をもとにした最適な検索クエリ（キーワード）を生成します。
     * 日付・時刻の取得には Carbon を使用します。
     */
    private function generateOptimizedQuery(string $messageContent): string
    {
        $nowStr = \Carbon\Carbon::now('Asia/Tokyo')->format('Y年m月d日');
        $context = str_replace('<current_time>', $nowStr, Messages::PROMPT_GENERATE_WEB_SEARCH_QUERY);

        $response = trim($this->gpt->getAnswer(
            context: $context,
            message: $messageContent,
        ));

        if (empty($response)) {
            return $messageContent;
        }

        return $response;
    }
}
