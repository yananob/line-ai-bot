<?php

declare(strict_types=1);

use MyApp\Conversations;

final class ConversationsTest extends PHPUnit\Framework\TestCase
{
    private Conversations $conversations;

    const TEST_CONVERSATIONS = [
        [1, "human", "今日は暑いね！"],
        [2, "bot", "そうですね！"],
        [3, "human", "今日は疲れたね！"],
        [4, "bot", "大変お疲れ様でした！"],
        [5, "human", "今日は眠いよ・・・"],
        [6, "bot", "金曜日ですもんね！"],
    ];
    private array $test_conversations;

    protected function setUp(): void
    {
        $this->conversations = new Conversations(targetId: "TARGET_ID_AUTOTEST", isTest: true);

        $this->test_conversations = [];
        foreach (self::TEST_CONVERSATIONS as $conversation) {
            $obj = new \stdClass();
            $obj->id = $conversation[0];
            $obj->by = $conversation[1];
            $obj->content = $conversation[2];
            // $obj->created_at = new Carbon("today");
            $this->test_conversations[] = $obj;
        }
    }

    /** 
     * テストデータ作成用
     */
    // public function testStoreRandomMessages()
    // {
    //     $this->conversations->store("human", "今日は暑いね！");
    //     $this->conversations->store("bot", "そうですね！");
    //     $this->conversations->store("human", "今日は疲れたね！");
    //     $this->conversations->store("bot", "大変お疲れ様でした！");
    //     $this->conversations->store("human", "今日は眠いよ・・・");
    //     $this->conversations->store("bot", "金曜日ですもんね！");
    //     $this->assertTrue(true);
    // }

    private function __removeColumns(array $ary, array $columns): array
    {
        $result = [];
        foreach ($ary as $row) {
            foreach ($columns as $column) {
                unset($row->$column);
            }
            $result[] = $row;
        }
        return $result;
    }

    public function testGet()
    {
        // bot + human
        $this->assertEquals(
            array_slice($this->test_conversations, 4, 2),
            $this->__removeColumns($this->conversations->get(includeBot: true, includeHuman: true, count: 2), ["created_at"])
        );

        // human
        $this->assertEquals(
            [$this->test_conversations[2], $this->test_conversations[4]],
            $this->__removeColumns($this->conversations->get(includeBot: false, includeHuman: true, count: 2), ["created_at"])
        );

        // bot
        $this->assertEquals(
            [$this->test_conversations[3], $this->test_conversations[5]],
            $this->__removeColumns($this->conversations->get(includeBot: true, includeHuman: false, count: 2), ["created_at"])
        );
    }

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
            $expected[] = $obj;
        };
        // krsort($expected);

        $convs = [];
        foreach ($this->conversations->get(count: count($expected)) as $conv) {
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
