<?php

declare(strict_types=1);

use Carbon\Carbon;
use MyApp\Conversations;

final class ConversationsTest extends PHPUnit\Framework\TestCase
{
    private Conversations $conversations;

    protected function setUp(): void
    {
        $this->conversations = new Conversations(targetId: "TEST_TARGET_ID", isTest: true);
    }

    // public function testGet()
    // {
    //     $this->assertEquals([], $this->conversations->get(5));
    //     // $this->assertNotEmpty($this->conversations->getAnswer("今年のクリスマスは何月何日でしょうか？\n昨年のクリスマスとは違うのでしょうか？"));
    // }

    public function testStoreAndGetAndDelete()
    {
        $expected = [];
        foreach (
            [
                ["by" => "human", "content" => "人の発言"],
                ["by" => "bot", "content" => "botの発言"],
            ] as $data
        ) {
            $this->conversations->store($data["by"], $data["content"]);

            $obj = new stdClass();
            // $obj->id = $data["id"];
            $obj->by = $data["by"];
            $obj->content = $data["content"];
            // $obj->created_at = new Carbon("today");
            array_unshift($expected, $obj);
        };
        // krsort($expected);

        $convs = [];
        foreach ($this->conversations->get(count($expected)) as $conv) {
            unset($conv->id);
            unset($conv->created_at);
            $convs[] = $conv;
        }

        $this->assertEquals($expected, $convs);

        $this->conversations->delete(count($expected));
    }

    public function testGet_returnsBlankForonExistanceTargetId()
    {
        $conversations = new Conversations(targetId: "NON_EXISTING_TARGET_ID", isTest: true);
        $this->assertSame([], $conversations->get());
    }
}
