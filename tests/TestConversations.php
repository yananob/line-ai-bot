<?php

declare(strict_types=1);

use MyApp\Conversations;

final class ConversationsTest extends PHPUnit\Framework\TestCase
{
    private Conversations $conversations;

    protected function setUp(): void
    {
        $this->conversations = new Conversations(targetId: "TEST_TARGET_ID", isTest: true);
    }

    public function testGet()
    {
        // $this->assertNotEmpty($this->conversations->getAnswer("今年のクリスマスは何月何日でしょうか？\n昨年のクリスマスとは違うのでしょうか？"));
    }

    public function testStore()
    {
        // $this->assertSame("TEST_LINE_TARGET", $this->conversations->getLineTarget());
    }
}
