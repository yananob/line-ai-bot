<?php declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use App\Domain\Bot\Consts;
use App\Domain\Bot\Messages;

final class ConstsAndMessagesTest extends TestCase
{
    public function test_Constsの定数が正しく定義されている(): void
    {
        $this->assertEquals("Asia/Tokyo", Consts::TIMEZONE);
        $this->assertEquals("delete_trigger", Consts::CMD_REMOVE_TRIGGER);
        $this->assertArrayHasKey(Consts::CMD_REMOVE_TRIGGER, Consts::CMD_LABELS);
        $this->assertEquals("削除", Consts::CMD_LABELS[Consts::CMD_REMOVE_TRIGGER]);
    }

    public function test_Messagesの定数が正しく定義されている(): void
    {
        $this->assertStringContainsString("何ができるかをお送りします", Messages::HELP);
        $this->assertStringContainsString("(1)メッセージや質問に回答する", Messages::HELP);
    }
}
