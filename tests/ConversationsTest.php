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
        $this->assertEquals([], $this->conversations->get(5));
        // $this->assertNotEmpty($this->conversations->getAnswer("今年のクリスマスは何月何日でしょうか？\n昨年のクリスマスとは違うのでしょうか？"));
    }

    public function testStore()
    {
        $this->conversations->store("human", "人の発言");
        $this->conversations->store("bot", "botの発言");

        $this->assertEquals([
            ["by" => "human", "content" => "人の発言", "created_at" => "2024/12/15"],
            ["by" => "bot", "content" => "botの発言", "created_at" => "2024/12/15"],
        ], $this->conversations->get(2));
        // $this->assertSame("TEST_LINE_TARGET", $this->conversations->getLineTarget());
    }
}
