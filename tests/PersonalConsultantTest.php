<?php

declare(strict_types=1);

use Carbon\Carbon;
use MyApp\PersonalConsultant;

final class PersonalConsultantTest extends PHPUnit\Framework\TestCase
{
    private PersonalConsultant $consultant_chat;
    private PersonalConsultant $consultant_consulting;
    private PersonalConsultant $consultant_default;

    protected function setUp(): void
    {
        $this->consultant_chat = new PersonalConsultant(__DIR__ . "/configs/config.json", "TARGET_ID_TEST_CHAT");
        $this->consultant_consulting = new PersonalConsultant(__DIR__ . "/configs/config.json", "TARGET_ID_TEST_CONSULTING");
        $this->consultant_default = new PersonalConsultant(__DIR__ . "/configs/config.json", "TARGET_ID_NOT_EXISTS");
    }

    private function __invokePrivateMethod($object, string $methodName, array $params): mixed
    {
        $reflection = new \ReflectionClass($object);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method->invoke($object, ...$params);
    }

    public function testGetAnswerWithoutRecentConversation()
    {
        $this->assertNotEmpty($this->consultant_chat->getAnswer(
            false,
            "今年のクリスマスは何月何日でしょうか？\n昨年のクリスマスとは違うのでしょうか？"
        ));
    }

    public function testGetAnswerWithRecentConversation()
    {
        $this->assertNotEmpty($this->consultant_chat->getAnswer(
            true,
            "今年のクリスマスは何月何日でしょうか？\n昨年のクリスマスとは違うのでしょうか？"
        ));
    }

    public function testGetContext_WithOutTargetConfiguration()
    {
        $this->assertStringNotContainsString(
            "【話し相手の情報】",
            $this->__invokePrivateMethod($this->consultant_default, "__getContext", [])
        );
    }
    public function testGetContext_WithTargetConfiguration()
    {
        $this->assertStringContainsString(
            "【話し相手の情報】",
            $this->__invokePrivateMethod($this->consultant_chat, "__getContext", [])
        );
    }

    public function testGetContext_WithoutRecentConversation()
    {
        $this->assertStringNotContainsString(
            "【最近の会話内容】",
            $this->__invokePrivateMethod($this->consultant_chat, "__getContext", [])
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
            "【最近の会話内容】",
            $this->__invokePrivateMethod($this->consultant_chat, "__getContext", $recentConversations)
        );
    }

    public function testGetRequest_ChatModeWithoutRecentConversations()
    {
        $result = $this->__invokePrivateMethod($this->consultant_chat, "__getRequest", [false]);
        foreach (
            [
                "返すメッセージの文字数は、話し相手からの今回のメッセージの文字数と同じぐらい",
            ]
            as $contain
        ) {
            $this->assertStringContainsString($contain, $result);
        }
        foreach (
            [
                "【話し相手の情報】の一部",
            ]
            as $contain
        ) {
            $this->assertStringNotContainsString($contain, $result);
        }
    }
    public function testGetRequest_ConsultingModeWithRecentConversations()
    {
        $result = $this->__invokePrivateMethod($this->consultant_consulting, "__getRequest", [true]);
        foreach (
            [
                "【話し相手の情報】の一部",
                "メッセージの文字数は、話し相手からの今回のメッセージの文字数の2倍ぐらい",
            ]
            as $contain
        ) {
            $this->assertStringContainsString($contain, $result);
        }
    }

    public function testGetLineTarget_WithTargetConfiguration()
    {
        $this->assertSame("LINE_TARGET_TEST", $this->consultant_chat->getLineTarget());
    }

    public function testGetLineTarget_WithOutTargetConfiguration()
    {
        $this->assertSame("LINE_TARGET_DEFAULT", $this->consultant_default->getLineTarget());
    }
}
