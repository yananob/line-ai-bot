<?php declare(strict_types=1);

namespace Tests\Domain\Bot\Service;

use PHPUnit\Framework\TestCase;
use App\Domain\Bot\Bot;
use App\Domain\Bot\Service\BotFactory;
use App\Domain\Bot\Trigger\TimerTrigger;

class BotFactoryTest extends TestCase
{
    public function testCreateBot(): void
    {
        $id = 'bot_123';
        $data = [
            'bot_name' => 'Test Bot',
            'bot_characteristics' => ['Friendly'],
            'human_characteristics' => ['Helpful'],
            'requests' => ['Say hello'],
            'line_target' => 'target_123'
        ];
        $trigger = new TimerTrigger('today', '10:00', 'Ping');
        $trigger->setId('t1');
        $triggers = ['t1' => $trigger];

        $bot = BotFactory::create($id, $data, $triggers);

        $this->assertInstanceOf(Bot::class, $bot);
        $this->assertSame($id, $bot->getId());
        $this->assertSame('Test Bot', $bot->getName());
        $this->assertSame(['Friendly'], $bot->getBotCharacteristics()->toArray());
        $this->assertSame(['Helpful'], $bot->getHumanCharacteristics()->toArray());
        $this->assertSame(['Say hello'], $bot->getConfigRequests(true, false)->toArray());
        $this->assertSame('target_123', $bot->getLineTarget());
        $this->assertSame($triggers, $bot->getTriggers());
    }

    public function testCreateBotWithDefaultBot(): void
    {
        $defaultBot = new Bot('default');
        $defaultBot->setBotCharacteristics(['Base']);

        $id = 'bot_123';
        $data = [
            'bot_characteristics' => ['Extra'],
        ];

        $bot = BotFactory::create($id, $data, [], $defaultBot);

        // getBotCharacteristics merges by default
        $this->assertSame(['Base', 'Extra'], $bot->getBotCharacteristics()->toArray());
    }
}
