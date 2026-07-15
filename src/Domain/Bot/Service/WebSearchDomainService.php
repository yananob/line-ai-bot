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
            return $this->webSearchTool->search($messageContent, 5);
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
}
