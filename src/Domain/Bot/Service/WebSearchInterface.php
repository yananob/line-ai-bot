<?php

declare(strict_types=1);

namespace App\Domain\Bot\Service;

interface WebSearchInterface
{
    /**
     * Performs a web search and returns a summary.
     *
     * @param string $query
     * @param int $numResults
     * @return string
     */
    public function search(string $query, int $numResults = 3): string;
}
