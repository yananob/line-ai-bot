<?php

declare(strict_types=1);

use Google\Cloud\Firestore\FirestoreClient;
use MyApp\BotConfig;
use MyApp\Mode;

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

    public function testGetRequests_withoutDefault()
    {
        $this->assertEquals([
            "回答を返して",
            "過去の会話を参照して",
        ], $this->botConfigWithoutDefault->getRequests());
    }

    public function testGetRequests_withDefault()
    {
        $this->assertEquals([
            "回答を返して",
            "過去の会話を参照して",
            "話し相手からのメッセージに対して、【最近の会話内容】を反映して、回答を返してください。",
            "過去にメモリーした内容は反映しないでください。",
        ], $this->botConfigWithDefault->getRequests());
    }

    public function testGetMode()
    {
        $this->assertSame(Mode::Chat->value, $this->botConfigWithDefault->getMode());
    }
    public function testIsChatMode()
    {
        $this->assertTrue($this->botConfigWithDefault->isChatMode());
    }
    public function testIsConsultingMode()
    {
        $this->assertFalse($this->botConfigWithDefault->isConsultingMode());
    }
    public function testGetLineTarget()
    {
        $this->assertSame("aisan", $this->botConfigWithDefault->getLineTarget());
    }
}
