<?php

declare(strict_types=1);

use Carbon\Carbon;
use MyApp\PersonalConsultant;

final class PersonalConsultantTest extends PHPUnit\Framework\TestCase
{
    private PersonalConsultant $consultant_defined;
    private PersonalConsultant $consultant_undefined;

    protected function setUp(): void
    {
        $this->consultant_defined = new PersonalConsultant(__DIR__ . "/configs/config.json", "TARGET_ID_TEST");
        $this->consultant_undefined = new PersonalConsultant(__DIR__ . "/configs/config.json", "TARGET_ID_NOT_EXISTS");
    }

    private function __invokePrivateMethod($object, string $methodName, array $params): mixed
    {
        $reflection = new \ReflectionClass($object);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method->invoke($object, $params);
    }

    public function testGetAnswerWithoutRecentConversation()
    {
        $this->assertNotEmpty($this->consultant_defined->getAnswer(
            false,
            "今年のクリスマスは何月何日でしょうか？\n昨年のクリスマスとは違うのでしょうか？"
        ));
    }

    public function testGetAnswerWithRecentConversation()
    {
        $this->assertNotEmpty($this->consultant_defined->getAnswer(
            true,
            "今年のクリスマスは何月何日でしょうか？\n昨年のクリスマスとは違うのでしょうか？"
        ));
    }

    public function testGetContext_WithOutTargetConfiguration()
    {
        $this->assertStringNotContainsString(
            "【話し相手の情報】",
            $this->__invokePrivateMethod($this->consultant_undefined, "__getContext", [])
        );
    }

    public function testGetContext_WithTargetConfiguration()
    {
        $this->assertStringContainsString(
            "【話し相手の情報】",
            $this->__invokePrivateMethod($this->consultant_defined, "__getContext", [])
        );
    }

    public function testGetContext_WithoutRecentConversation()
    {
        $this->assertStringNotContainsString(
            "【最近の会話内容】",
            $this->__invokePrivateMethod($this->consultant_defined, "__getContext", [])
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
            $this->__invokePrivateMethod($this->consultant_defined, "__getContext", $recentConversations)
        );
    }

    public function testGetLineTarget_WithTargetConfiguration()
    {
        $this->assertSame("LINE_TARGET_TEST", $this->consultant_defined->getLineTarget());
    }

    public function testGetLineTarget_WithOutTargetConfiguration()
    {
        $this->assertSame("LINE_TARGET_DEFAULT", $this->consultant_undefined->getLineTarget());
    }
}
