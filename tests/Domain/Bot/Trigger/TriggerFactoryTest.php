<?php declare(strict_types=1);

namespace Tests\Domain\Bot\Trigger;

use PHPUnit\Framework\TestCase;
use App\Domain\Bot\Trigger\TriggerFactory;
use App\Domain\Bot\Trigger\TimerTrigger;

class TriggerFactoryTest extends TestCase
{
    public function testFromArrayWithTimerEvent(): void
    {
        $id = 'trigger_123';
        $data = [
            'event' => 'timer',
            'date' => '2023-12-25',
            'time' => '12:00',
            'request' => 'Hello'
        ];

        $trigger = TriggerFactory::fromArray($id, $data);

        $this->assertInstanceOf(TimerTrigger::class, $trigger);
        $this->assertSame($id, $trigger->getId());
        $this->assertSame('2023-12-25', $trigger->getDate());
        $this->assertSame('12:00', $trigger->getTime());
        $this->assertSame('Hello', $trigger->getRequest());
    }

    public function testFromArrayWithUnknownEvent(): void
    {
        $id = 'trigger_123';
        $data = [
            'event' => 'unknown',
        ];

        $trigger = TriggerFactory::fromArray($id, $data);

        $this->assertNull($trigger);
    }

    public function testFromArrayWithMissingEvent(): void
    {
        $id = 'trigger_123';
        $data = [
            'date' => '2023-12-25',
        ];

        $trigger = TriggerFactory::fromArray($id, $data);

        $this->assertNull($trigger);
    }
}
