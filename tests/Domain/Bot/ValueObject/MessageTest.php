<?php

declare(strict_types=1);

namespace Tests\Domain\Bot\ValueObject;

use App\Domain\Bot\ValueObject\Message;
use PHPUnit\Framework\TestCase;

final class MessageTest extends TestCase
{
    public function test_内容とシステムフラグを保持する(): void
    {
        $message = new Message("Hello world", false);
        $this->assertSame("Hello world", $message->getContent());
        $this->assertFalse($message->isSystem());

        $systemMessage = new Message("System alert", true);
        $this->assertSame("System alert", $systemMessage->getContent());
        $this->assertTrue($systemMessage->isSystem());
    }

    public function test_システムフラグのデフォルト値がfalseであることを確認する(): void
    {
        $message = new Message("Default message");
        $this->assertFalse($message->isSystem());
    }

    public function test_文字列に変換できる(): void
    {
        $message = new Message("Hello");
        $this->assertSame("Hello", (string)$message);
    }
}
