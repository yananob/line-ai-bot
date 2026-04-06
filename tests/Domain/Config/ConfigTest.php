<?php

declare(strict_types=1);

namespace Tests\Domain\Config;

use App\Domain\Config\Config;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    public function test_IDとデータを保持する(): void
    {
        $data = ['key' => 'value'];
        $config = new Config('bot_123', $data);

        $this->assertSame('bot_123', $config->getId());
        $this->assertSame($data, $config->getData());
    }

    public function test_データを更新できる(): void
    {
        $config = new Config('bot_123', ['old' => 'data']);
        $newData = ['new' => 'data'];
        $config->setData($newData);

        $this->assertSame($newData, $config->getData());
    }
}
