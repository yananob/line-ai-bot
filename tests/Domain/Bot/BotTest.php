<?php

declare(strict_types=1);

namespace MyApp\Tests\Domain\Bot;

use PHPUnit\Framework\TestCase;
use MyApp\Domain\Bot\Bot;
use MyApp\Domain\Bot\Trigger\TimerTrigger;
use MyApp\Domain\Bot\ValueObject\StringList;

final class BotTest extends TestCase
{
    private Bot $bot;

    protected function setUp(): void
    {
        $this->bot = new Bot("testBotId", null);
    }

    public function test_IDを取得する(): void
    {
        $this->assertEquals("testBotId", $this->bot->getId());
    }

    public function test_ボットの特性を設定および取得する(): void
    {
        $chars = ["特性1", "特性2"];
        $this->bot->setBotCharacteristics($chars);
        $this->assertEquals($chars, $this->bot->getBotCharacteristics()->toArray());
    }

    public function test_ボットの特性が設定されておらずデフォルトが提供されている場合にデフォルトを返す(): void
    {
        $defaultBot = new Bot("defaultBotId");
        $defaultBot->setBotCharacteristics(['デフォルト特性']);
        $botWithDefault = new Bot("botWithDef", $defaultBot);
        $this->assertEquals(['デフォルト特性'], $botWithDefault->getBotCharacteristics()->toArray());

        // 個別設定がある場合はそちらが優先される
        $botWithDefault->setBotCharacteristics(['個別特性']);
        $this->assertEquals(['個別特性'], $botWithDefault->getBotCharacteristics()->toArray());
    }

    public function test_人間の特性を設定および取得する(): void
    {
        $chars = ["人間の特性1"];
        $this->bot->setHumanCharacteristics($chars);
        $this->assertEquals($chars, $this->bot->getHumanCharacteristics()->toArray());
        $this->assertTrue($this->bot->hasHumanCharacteristics());
    }

    public function test_人間の特性が空の場合にhasHumanCharacteristicsがFalseを返す(): void
    {
        $this->bot->setHumanCharacteristics([]);
        $this->assertFalse($this->bot->hasHumanCharacteristics());
    }

    public function test_人間の特性が設定されておらずデフォルトが提供されている場合にデフォルトを確認する(): void
    {
        $defaultBot = new Bot("defaultBotId");
        $defaultBot->setHumanCharacteristics(['デフォルト人間特性']);
        $botWithDefault = new Bot("botWithDef", $defaultBot);

        $this->assertTrue($botWithDefault->hasHumanCharacteristics());
        $this->assertEquals(['デフォルト人間特性'], $botWithDefault->getHumanCharacteristics()->toArray());

        // 個別設定が空でもデフォルトがあればTrue
        $botWithDefault->setHumanCharacteristics([]);
        $this->assertTrue($botWithDefault->hasHumanCharacteristics());
        $this->assertEquals(['デフォルト人間特性'], $botWithDefault->getHumanCharacteristics()->toArray());
    }

    public function test_設定リクエストを設定および取得する(): void
    {
        $reqs = ["リクエストA", "リクエストB"];
        $this->bot->setConfigRequests($reqs);
        $this->assertEquals($reqs, $this->bot->getConfigRequests(true, false)->toArray());
    }

    public function test_設定リクエストがデフォルトとマージされることを確認する(): void
    {
        $defaultBot = new Bot("defaultBotId");
        $defaultBot->setConfigRequests(['デフォルトリクエスト']);

        $bot = new Bot("myBot", $defaultBot);
        $bot->setConfigRequests(['個別リクエスト']);

        $allRequests = $bot->getConfigRequests(true, true);
        $this->assertEquals(['デフォルトリクエスト', '個別リクエスト'], $allRequests->toArray());

        // 個別のみ
        $personalOnly = $bot->getConfigRequests(true, false);
        $this->assertEquals(['個別リクエスト'], $personalOnly->toArray());

        // デフォルトのみ
        $defaultOnly = $bot->getConfigRequests(false, true);
        $this->assertEquals(['デフォルトリクエスト'], $defaultOnly->toArray());
    }

    public function test_LINEターゲットを設定および取得する(): void
    {
        $target = "test_target_123";
        $this->bot->setLineTarget($target);
        $this->assertEquals($target, $this->bot->getLineTarget());
    }

    public function test_LINEターゲットが設定されていない場合にデフォルトから取得する(): void
    {
        $defaultBot = new Bot("defaultBotId");
        $defaultBot->setLineTarget("default_target");

        $bot = new Bot("myBot", $defaultBot);
        $this->assertEquals("default_target", $bot->getLineTarget());

        $bot->setLineTarget("personal_target");
        $this->assertEquals("personal_target", $bot->getLineTarget());
    }

    public function test_トリガーを追加および取得する(): void
    {
        $this->assertCount(0, $this->bot->getTriggers());
        $trigger1 = new TimerTrigger("today", "10:00", "リクエスト1");
        $triggerId1 = $this->bot->addTrigger($trigger1);

        $this->assertNotEmpty($triggerId1);
        $this->assertCount(1, $this->bot->getTriggers());
        $triggersArray = array_values($this->bot->getTriggers());
        $this->assertSame($trigger1, $triggersArray[0]);
        $this->assertEquals($triggerId1, $trigger1->getId());

        $trigger2 = new TimerTrigger("everyday", "12:00", "リクエスト2");
        $this->bot->addTrigger($trigger2);
        $this->assertCount(2, $this->bot->getTriggers());
    }

    public function test_トリガーを削除する(): void
    {
        $trigger1 = new TimerTrigger("today", "10:00", "リクエスト1");
        $id1 = $this->bot->addTrigger($trigger1);

        $trigger2 = new TimerTrigger("tomorrow", "11:00", "リクエスト2");
        $id2 = $this->bot->addTrigger($trigger2);

        $this->assertCount(2, $this->bot->getTriggers());
        $this->bot->deleteTriggerById($id1);
        $this->assertCount(1, $this->bot->getTriggers());

        $remainingTriggers = array_values($this->bot->getTriggers());
        $this->assertSame($trigger2, $remainingTriggers[0]);

        $this->bot->deleteTriggerById($id2);
        $this->assertCount(0, $this->bot->getTriggers());
    }

    public function test_存在しないトリガーを削除する(): void
    {
        $trigger1 = new TimerTrigger("today", "10:00", "リクエスト1");
        $this->bot->addTrigger($trigger1);
        $this->assertCount(1, $this->bot->getTriggers());
        $this->bot->deleteTriggerById("存在しないID");
        $this->assertCount(1, $this->bot->getTriggers());
    }
}
