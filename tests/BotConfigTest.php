<?php

declare(strict_types=1);

use Google\Cloud\Firestore\FirestoreClient;
use MyApp\BotConfig;
use MyApp\TimerTrigger;

final class BotConfigTest extends PHPUnit\Framework\TestCase
{
    private BotConfig $botConfigWithoutDefault;
    private BotConfig $botConfigWithDefault;
    private BotConfig $botConfigNotExists;

    protected function setUp(): void
    {
        $dbAccessor = new FirestoreClient(["keyFilePath" => __DIR__ . '/../configs/firebase.json']);
        $documentRoot = $dbAccessor->collection("ai-bot-test")->document("configs");
        $this->botConfigWithoutDefault = new BotConfig($documentRoot->collection("TARGET_ID_AUTOTEST"), null);
        $this->botConfigWithDefault = new BotConfig(
            $documentRoot->collection("TARGET_ID_AUTOTEST"),
            new BotConfig($documentRoot->collection("default"), null),
        );
        $this->botConfigNotExists = new BotConfig($documentRoot->collection("TARGET_ID_NOT_EXISTS"), null);
    }

    public function testGetBotCharacteristics_exists()
    {
        $this->assertEquals([
            "丁寧なチャットボット",
            "プログラミングの知識が豊富",
        ], $this->botConfigWithoutDefault->getBotCharacteristics());
    }
    public function testGetBotCharacteristics_notExists()
    {
        $this->assertEquals([], $this->botConfigNotExists->getBotCharacteristics());
    }

    public function testGetHumanCharacteristics_exists()
    {
        $this->assertEquals([
            "男性",
            "年齢：40代",
            "趣味：ランニング",
        ], $this->botConfigWithoutDefault->getHumanCharacteristics());
    }
    public function testGetHumanCharacteristics_notExists()
    {
        $this->assertEquals([], $this->botConfigNotExists->getHumanCharacteristics());
    }

    public function testHasHumanCharacteristics_exists()
    {
        $this->assertTrue($this->botConfigWithoutDefault->hasHumanCharacteristics());
    }
    public function testHasHumanCharacteristics_notExists()
    {
        $this->assertFalse($this->botConfigNotExists->hasHumanCharacteristics());
    }

    public function testGetConfigRequests_withoutDefault()
    {
        $this->assertEquals([
            "口調は武士で",
        ], $this->botConfigWithoutDefault->getConfigRequests(usePersonal: true, useDefault: false));
    }

    public function testGetConfigRequests_withDefaultUsingDefault()
    {
        $this->assertEquals([
            "口調は武士で",
            "話し相手からのメッセージに対して、【最近の会話内容】を反映して、回答を返してください。",
            "【話し相手の情報】の内容がある場合は、その内容を少しだけ踏まえた回答にしてください。",
        ], $this->botConfigWithDefault->getConfigRequests(usePersonal: true, useDefault: true));
    }

    public function testGetConfigRequests_withDefaultNotUsingPersonal()
    {
        $this->assertEquals([
            "話し相手からのメッセージに対して、【最近の会話内容】を反映して、回答を返してください。",
            "【話し相手の情報】の内容がある場合は、その内容を少しだけ踏まえた回答にしてください。",
        ], $this->botConfigWithDefault->getConfigRequests(usePersonal: false, useDefault: true));
    }

    public function testGetLineTarget()
    {
        $this->assertSame("LINE_TARGET_TEST", $this->botConfigWithDefault->getLineTarget());
    }
    
    public function testGetTriggers()
    {
        $triggers = $this->botConfigWithDefault->getTriggers();
        $this->assertEquals(
            ["timer", "timer"],
            array_map(function ($trigger) {
                return $trigger->getEvent();
            }, $triggers)
        );
        $this->assertEquals(
            ["16:00", "14:20"],
            array_map(function ($trigger) {
                return $trigger->getTime();
            }, $triggers)
        );
    }

    public function testGetTriggerRequests()
    {
        $this->assertEquals([
            "話し相手からのメッセージに対して、【最近の会話内容】を反映して、回答を返してください。",
        ], $this->botConfigWithDefault->getTriggerRequests());
    }

    public function testAddTrigger()
    {
        $trigger = new TimerTrigger("2024/1/1", "11:30", "モーニングメッセージを送って");
        $id = $this->botConfigWithDefault->addTrigger($trigger);
        $triggersNew = $this->botConfigWithDefault->getTriggers();
        foreach ($triggersNew as $triggerNew) {
            if (($trigger->getEvent() === $triggerNew->getEvent()) &&
                ($trigger->getDate() === $triggerNew->getDate()) &&
                ($trigger->getTime() === $triggerNew->getTime()) &&
                ($trigger->getRequest() === $triggerNew->getRequest())
            ) {
                $this->assertTrue(true);
            }
        }
        $this->botConfigWithDefault->deleteTriggerById($id);
    }
}
