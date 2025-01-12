<?php

declare(strict_types=1);

use Carbon\Carbon;
use MyApp\LogicBot;
use yananob\MyTools\Test;
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
            Test::invokePrivateMethod(
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
            Test::invokePrivateMethod(
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
            Test::invokePrivateMethod(
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
            Test::invokePrivateMethod(
                $this->bot,
                "__getContext",
                $recentConversations,
                ["話し相手からのメッセージに対して、【最近の会話内容】を反映して、回答を返してください。"]
            )
        );
    }

    public function testGetLineTarget_WithTargetConfiguration()
    {
        $this->assertSame("test", $this->bot->getLineTarget());
    }

    public function testGetLineTarget_WithOutTargetConfiguration()
    {
        $this->assertSame("test", $this->bot_default->getLineTarget());
    }

    public function testAddTimerTrigger(): void
    {
        $logicBot = new LogicBot();

        $trigger = $logicBot->generateOneTimeTrigger("1時間後に「できたよ」と送って");
        $id = $this->bot->addTimerTrigger($trigger);
        $this->bot->deleteTrigger($id);

        $trigger = $logicBot->generateOneTimeTrigger("11時半に「ご飯だよ」と送って");
        $id = $this->bot->addTimerTrigger($trigger);
        $this->bot->deleteTrigger($id);

        $trigger = $logicBot->generateDailyTrigger("毎日7時半に天気予報を送って");
        $id = $this->bot->addTimerTrigger($trigger);
        $this->bot->deleteTrigger($id);

        $this->assertTrue(true);
    }
}
