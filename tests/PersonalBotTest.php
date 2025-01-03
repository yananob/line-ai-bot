<?php

declare(strict_types=1);

use Carbon\Carbon;
use MyApp\PersonalBot;

final class PersonalBotTest extends PHPUnit\Framework\TestCase
{
    private PersonalBot $bot;
    private PersonalBot $bot_default;

    protected function setUp(): void
    {
        // $this->bot_chat = new PersonalBot("TARGET_ID_TEST_CHAT");
        // $this->bot_consulting = new PersonalBot("TARGET_ID_TEST_CONSULTING");
        $this->bot = new PersonalBot("TARGET_ID_AUTOTEST");
        $this->bot_default = new PersonalBot("TARGET_ID_NOT_EXISTS");
    }

    private function __invokePrivateMethod($object, string $methodName, ...$args): mixed
    {
        $reflection = new \ReflectionClass($object);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method->invoke($object, ...$args);
    }

    public function testGetAnswerWithoutRecentConversation()
    {
        $this->assertNotEmpty($this->bot->getAnswer(
            false,
            "今年のクリスマスは何月何日でしょうか？\n昨年のクリスマスとは違うのでしょうか？"
        ));
    }

    public function testGetAnswerWithRecentConversation()
    {
        $this->assertNotEmpty($this->bot->getAnswer(
            true,
            "今年のクリスマスは何月何日でしょうか？\n昨年のクリスマスとは違うのでしょうか？"
        ));
    }

    public function testAskRequestWithoutRecentConversation()
    {
        $this->assertNotEmpty($this->bot->askRequest(
            false,
            "今年のクリスマスメッセージを送って"
        ));
    }

    public function testGetContext_WithOutTargetConfiguration()
    {
        $this->assertStringNotContainsString(
            "【話し相手の情報】\n",
            $this->__invokePrivateMethod(
                $this->bot_default,
                "__getContext",
                [],
                ["話し相手からのメッセージに対して、【最近の会話内容】を反映して、回答を返してください。"]
            )
        );
    }
    public function testGetContext_WithTargetConfiguration()
    {
        $this->assertStringContainsString(
            "【話し相手の情報】\n",
            $this->__invokePrivateMethod(
                $this->bot,
                "__getContext",
                [],
                ["話し相手からのメッセージに対して、【最近の会話内容】を反映して、回答を返してください。"]
            )
        );
    }

    public function testGetContext_WithoutRecentConversation()
    {
        $this->assertStringNotContainsString(
            "【最近の会話内容】\n",
            $this->__invokePrivateMethod(
                $this->bot_default,
                "__getContext",
                [],
                ["話し相手からのメッセージに対して、【最近の会話内容】を反映して、回答を返してください。"]
            )
        );
    }
    public function testGetContext_WithRecentConversation()
    {
        $recentConversations = [];
        $obj = new stdClass();
        $obj->by = "human";
        $obj->content = "今日は旅行に行きました";
        $obj->created_at = new Carbon("today");
        $recentConversations[] = $obj;

        $this->assertStringContainsString(
            "【最近の会話内容】\n",
            $this->__invokePrivateMethod(
                $this->bot,
                "__getContext",
                $recentConversations,
                ["話し相手からのメッセージに対して、【最近の会話内容】を反映して、回答を返してください。"]
            )
        );
    }

    // public function testGetRequest_ChatModeWithoutRecentConversations()
    // {
    //     $result = $this->__invokePrivateMethod($this->bot, "__getRequest", false);
    //     foreach (
    //         [
    //             "返すメッセージの文字数は、話し相手からの今回のメッセージの文字数と同じぐらい",
    //         ]
    //         as $contain
    //     ) {
    //         $this->assertStringContainsString($contain, $result);
    //     }
    //     foreach (
    //         [
    //             "【話し相手の情報】の一部",
    //         ]
    //         as $notContain
    //     ) {
    //         $this->assertStringNotContainsString($notContain, $result);
    //     }
    // }
    // public function testGetRequest_ConsultingModeWithRecentConversations()
    // {
    //     $result = $this->__invokePrivateMethod($this->bot_consulting, "__getRequest", true);
    //     foreach (
    //         [
    //             "【話し相手の情報】の一部",
    //             "メッセージの文字数は、話し相手からの今回のメッセージの文字数の2倍ぐらい",
    //         ]
    //         as $contain
    //     ) {
    //         $this->assertStringContainsString($contain, $result);
    //     }
    // }

    public function testGetLineTarget_WithTargetConfiguration()
    {
        $this->assertSame("LINE_TARGET_TEST", $this->bot->getLineTarget());
    }

    public function testGetLineTarget_WithOutTargetConfiguration()
    {
        $this->assertSame("LINE_TARGET_DEFAULT", $this->bot_default->getLineTarget());
    }
}
