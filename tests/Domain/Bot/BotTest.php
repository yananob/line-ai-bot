<?php

declare(strict_types=1);

namespace MyApp\Tests\Domain\Bot; // 名前空間は既に存在していました

use PHPUnit\Framework\TestCase;
use MyApp\Domain\Bot\Bot;
use MyApp\Domain\Bot\Trigger\TimerTrigger;
// Botがデフォルト値にBotConfigを使用している場合、MyApp\BotConfigをモックする必要があるかもしれません
// このテストでは、Botはほとんど独立しているか、デフォルト設定が単純であると仮定します。

final class BotTest extends \PHPUnit\Framework\TestCase // TestCaseの完全修飾名を使用
{
    private Bot $bot;
    // private $mockDefaultBotConfig; // Botがデフォルト値にMyApp\BotConfigを使用する場合のモック

    protected function setUp(): void
    {
        // MyApp\BotConfigがBot.phpコンストラクタのデフォルト値の依存関係である場合
        // $this->mockDefaultBotConfig = $this->createMock(\MyApp\BotConfig::class);
        // $this->mockDefaultBotConfig->method('getBotCharacteristics')->willReturn(['デフォルトのボット特性']);
        // $this->mockDefaultBotConfig->method('getHumanCharacteristics')->willReturn(['デフォルトの人間特性']);
        // $this->mockDefaultBotConfig->method('getConfigRequests')->willReturn(['デフォルトリクエスト']);
        // $this->mockDefaultBotConfig->method('getLineTarget')->willReturn('default_target');

        // 現在のBotコンストラクタ: public function __construct(string $id, ?MyApp\BotConfig $configDefault = null)
        // ここでデフォルトのフォールバックをテストしていない場合はnullを渡すか、モックを渡します。
        $this->bot = new Bot("testBotId", null /* $this->mockDefaultBotConfig */);
    }

    public function test_IDを取得する(): void
    {
        $this->assertEquals("testBotId", $this->bot->getId());
    }

    public function test_ボットの特性を設定および取得する(): void
    {
        $chars = ["特性1", "特性2"];
        $this->bot->setBotCharacteristics($chars);
        $this->assertEquals($chars, $this->bot->getBotCharacteristics());
    }

    public function test_ボットの特性が設定されておらずデフォルトが提供されている場合にデフォルトを返す(): void
    {
        // このテストでは、Botコンストラクタがモック可能なデフォルト設定を取る必要があります
        // そして、Bot::getBotCharacteristicsがそれにフォールバックするロジックを持つ必要があります。
        // Bot.phpのgetBotCharacteristicsがこれに合わせて更新されたと仮定します:
        // if (empty($this->botCharacteristics) && $this->configDefault) {
        //     return $this->configDefault->getBotCharacteristics();
        // }
        $mockDefaultConfig = $this->createMock(\MyApp\BotConfig::class); // これは古いBotConfigをモックします
        $mockDefaultConfig->method('getBotCharacteristics')->willReturn(['デフォルト特性']);
        $botWithDefault = new Bot("botWithDef", $mockDefaultConfig);
        $this->assertEquals(['デフォルト特性'], $botWithDefault->getBotCharacteristics());
    }

    public function test_人間の特性を設定および取得する(): void
    {
        $chars = ["人間の特性1"];
        $this->bot->setHumanCharacteristics($chars);
        $this->assertEquals($chars, $this->bot->getHumanCharacteristics());
        $this->assertTrue($this->bot->hasHumanCharacteristics());
    }

    public function test_人間の特性が空の場合にhasHumanCharacteristicsがFalseを返す(): void
    {
        $this->bot->setHumanCharacteristics([]);
        $this->assertFalse($this->bot->hasHumanCharacteristics());
    }

    public function test_設定リクエストを設定および取得する(): void
    {
        $reqs = ["リクエストA", "リクエストB"];
        $this->bot->setConfigRequests($reqs);
        // getConfigRequests(true, false) が個人リクエストを返すと仮定
        $this->assertEquals($reqs, $this->bot->getConfigRequests(true, false));
    }

    public function test_LINEターゲットを設定および取得する(): void
    {
        $target = "test_target_123";
        $this->bot->setLineTarget($target);
        $this->assertEquals($target, $this->bot->getLineTarget());
    }

    public function test_トリガーを追加および取得する(): void
    {
        $this->assertCount(0, $this->bot->getTriggers());
        $trigger1 = new TimerTrigger("今日", "10:00", "リクエスト1");
        $triggerId1 = $this->bot->addTrigger($trigger1);

        $this->assertNotEmpty($triggerId1);
        $this->assertCount(1, $this->bot->getTriggers());
        // BotはトリガーをIDによる連想配列に格納します。アサーションのために最初のものを取得するには:
        $triggersArray = array_values($this->bot->getTriggers()); // トリガーを単純なインデックス配列として取得
        $this->assertSame($trigger1, $triggersArray[0]);
        $this->assertEquals($triggerId1, $trigger1->getId());

        $trigger2 = new TimerTrigger("毎日", "12:00", "リクエスト2");
        $this->bot->addTrigger($trigger2);
        $this->assertCount(2, $this->bot->getTriggers());
    }

    public function test_トリガーを削除する(): void
    {
        $trigger1 = new TimerTrigger("今日", "10:00", "リクエスト1");
        $id1 = $this->bot->addTrigger($trigger1);

        $trigger2 = new TimerTrigger("明日", "11:00", "リクエスト2");
        $id2 = $this->bot->addTrigger($trigger2);

        $this->assertCount(2, $this->bot->getTriggers());
        $this->bot->deleteTriggerById($id1);
        $this->assertCount(1, $this->bot->getTriggers());

        $remainingTriggers = array_values($this->bot->getTriggers());
        $this->assertSame($trigger2, $remainingTriggers[0]); // 残りのトリガーを確認

        $this->bot->deleteTriggerById($id2);
        $this->assertCount(0, $this->bot->getTriggers());
    }

    public function test_存在しないトリガーを削除する(): void
    {
        $trigger1 = new TimerTrigger("今日", "10:00", "リクエスト1");
        $this->bot->addTrigger($trigger1);
        $this->assertCount(1, $this->bot->getTriggers());
        $this->bot->deleteTriggerById("存在しないID"); // エラーをスローせず、何もしないはず
        $this->assertCount(1, $this->bot->getTriggers());
    }
}
