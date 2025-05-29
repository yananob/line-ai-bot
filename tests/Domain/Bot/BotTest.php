<?php

declare(strict_types=1);

namespace MyApp\Tests\Domain\Bot; // Assuming tests are namespaced

use PHPUnit\Framework\TestCase;
use MyApp\Domain\Bot\Bot;
use MyApp\Domain\Bot\Trigger\TimerTrigger;
// If Bot uses BotConfig for default values, you might need to mock MyApp\BotConfig
// For this test, we'll assume Bot is mostly independent or default config is simple.

final class BotTest extends TestCase
{
    private Bot $bot;
    private $mockDefaultBotConfig; // Mock for MyApp\BotConfig if Bot uses it for defaults

    protected function setUp(): void
    {
        // If MyApp\BotConfig is still a dependency for default values in Bot.php constructor
        // $this->mockDefaultBotConfig = $this->createMock(\MyApp\BotConfig::class);
        // $this->mockDefaultBotConfig->method('getBotCharacteristics')->willReturn(['Default Bot Char']);
        // $this->mockDefaultBotConfig->method('getHumanCharacteristics')->willReturn(['Default Human Char']);
        // $this->mockDefaultBotConfig->method('getConfigRequests')->willReturn(['Default Request']);
        // $this->mockDefaultBotConfig->method('getLineTarget')->willReturn('default_target');

        // Current Bot constructor: public function __construct(string $id, ?MyApp\BotConfig $configDefault = null)
        // Pass null if we are not testing default fallbacks here, or pass the mock.
        $this->bot = new Bot("testBotId", null /* $this->mockDefaultBotConfig */);
    }

    public function testGetId(): void
    {
        $this->assertEquals("testBotId", $this->bot->getId());
    }

    public function testSetAndGetBotCharacteristics(): void
    {
        $chars = ["Characteristic 1", "Characteristic 2"];
        $this->bot->setBotCharacteristics($chars);
        $this->assertEquals($chars, $this->bot->getBotCharacteristics());
    }

    public function testGetBotCharacteristicsReturnsDefaultWhenNotSetAndDefaultProvided(): void
    {
        // This test requires Bot constructor to take a mockable default config
        // And Bot::getBotCharacteristics to have logic to fall back to it.
        // Assuming Bot.php's getBotCharacteristics was updated for this:
        // if (empty($this->botCharacteristics) && $this->configDefault) {
        //     return $this->configDefault->getBotCharacteristics();
        // }
        $mockDefaultConfig = $this->createMock(\MyApp\BotConfig::class); // This will mock the old BotConfig
        $mockDefaultConfig->method('getBotCharacteristics')->willReturn(['Default Char']);
        $botWithDefault = new Bot("botWithDef", $mockDefaultConfig);
        $this->assertEquals(['Default Char'], $botWithDefault->getBotCharacteristics());
    }
    
    public function testSetAndGetHumanCharacteristics(): void
    {
        $chars = ["Human Trait 1"];
        $this->bot->setHumanCharacteristics($chars);
        $this->assertEquals($chars, $this->bot->getHumanCharacteristics());
        $this->assertTrue($this->bot->hasHumanCharacteristics());
    }

    public function testHasHumanCharacteristicsFalseWhenEmpty(): void
    {
        $this->bot->setHumanCharacteristics([]);
        $this->assertFalse($this->bot->hasHumanCharacteristics());
    }

    public function testSetAndGetConfigRequests(): void
    {
        $reqs = ["Request A", "Request B"];
        $this->bot->setConfigRequests($reqs);
        // Assuming getConfigRequests(true, false) returns personal requests
        $this->assertEquals($reqs, $this->bot->getConfigRequests(true, false));
    }

    public function testSetAndGetLineTarget(): void
    {
        $target = "test_target_123";
        $this->bot->setLineTarget($target);
        $this->assertEquals($target, $this->bot->getLineTarget());
    }

    public function testAddAndGetTriggers(): void
    {
        $this->assertCount(0, $this->bot->getTriggers());
        $trigger1 = new TimerTrigger("today", "10:00", "Request 1");
        $triggerId1 = $this->bot->addTrigger($trigger1);
        
        $this->assertNotEmpty($triggerId1);
        $this->assertCount(1, $this->bot->getTriggers());
        // Bot stores triggers in an associative array by ID. To get the first one for assertion:
        $triggersArray = array_values($this->bot->getTriggers()); // Get triggers as a simple indexed array
        $this->assertSame($trigger1, $triggersArray[0]);
        $this->assertEquals($triggerId1, $trigger1->getId());

        $trigger2 = new TimerTrigger("everyday", "12:00", "Request 2");
        $this->bot->addTrigger($trigger2);
        $this->assertCount(2, $this->bot->getTriggers());
    }

    public function testDeleteTrigger(): void
    {
        $trigger1 = new TimerTrigger("today", "10:00", "Request 1");
        $id1 = $this->bot->addTrigger($trigger1);

        $trigger2 = new TimerTrigger("tomorrow", "11:00", "Request 2");
        $id2 = $this->bot->addTrigger($trigger2);

        $this->assertCount(2, $this->bot->getTriggers());
        $this->bot->deleteTriggerById($id1);
        $this->assertCount(1, $this->bot->getTriggers());
        
        $remainingTriggers = array_values($this->bot->getTriggers());
        $this->assertSame($trigger2, $remainingTriggers[0]); // Check remaining trigger

        $this->bot->deleteTriggerById($id2);
        $this->assertCount(0, $this->bot->getTriggers());
    }

    public function testDeleteNonExistentTrigger(): void
    {
        $trigger1 = new TimerTrigger("today", "10:00", "Request 1");
        $this->bot->addTrigger($trigger1);
        $this->assertCount(1, $this->bot->getTriggers());
        $this->bot->deleteTriggerById("non_existent_id"); // Should not throw error, just do nothing
        $this->assertCount(1, $this->bot->getTriggers());
    }
}
