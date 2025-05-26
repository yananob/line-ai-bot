<?php

declare(strict_types=1);

namespace MyApp\Tests;

use PHPUnit\Framework\TestCase;
use MyApp\WebSearchTool; // Ensure this use statement is added

class WebSearchToolTest extends TestCase
{
    public function testSearchReturnsPlaceholder(): void
    {
        $query = "test query";
        $expectedResult = "Placeholder search results for query: " . $query;
        $this->assertSame($expectedResult, WebSearchTool::search($query));
    }
}
