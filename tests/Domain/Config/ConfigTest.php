<?php

declare(strict_types=1);

namespace Tests\Domain\Config;

use App\Domain\Config\Config;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    public function test_it_stores_id_and_data(): void
    {
        $data = ['key' => 'value'];
        $config = new Config('bot_123', $data);

        $this->assertSame('bot_123', $config->getId());
        $this->assertSame($data, $config->getData());
    }

    public function test_it_can_update_data(): void
    {
        $config = new Config('bot_123', ['old' => 'data']);
        $newData = ['new' => 'data'];
        $config->setData($newData);

        $this->assertSame($newData, $config->getData());
    }
}
