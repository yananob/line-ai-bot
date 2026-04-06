<?php

declare(strict_types=1);

namespace Tests\Domain\Bot\ValueObject;

use App\Domain\Bot\ValueObject\Message;
use PHPUnit\Framework\TestCase;

final class MessageTest extends TestCase
{
    public function test_it_stores_content_and_is_system_flag(): void
    {
        $message = new Message("Hello world", false);
        $this->assertSame("Hello world", $message->getContent());
        $this->assertFalse($message->isSystem());

        $systemMessage = new Message("System alert", true);
        $this->assertSame("System alert", $systemMessage->getContent());
        $this->assertTrue($systemMessage->isSystem());
    }

    public function test_is_system_defaults_to_false(): void
    {
        $message = new Message("Default message");
        $this->assertFalse($message->isSystem());
    }

    public function test_it_can_be_converted_to_string(): void
    {
        $message = new Message("Hello");
        $this->assertSame("Hello", (string)$message);
    }
}
