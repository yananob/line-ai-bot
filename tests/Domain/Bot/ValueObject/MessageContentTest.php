<?php declare(strict_types=1);

namespace Tests\Domain\Bot\ValueObject;

use App\Domain\Bot\ValueObject\MessageContent;
use PHPUnit\Framework\TestCase;

class MessageContentTest extends TestCase
{
    public function test_it_trims_the_value(): void
    {
        $content = new MessageContent("  hello world  \n");
        $this->assertEquals("hello world", $content->getValue());
        $this->assertEquals("hello world", (string)$content);
    }

    public function test_it_can_be_empty(): void
    {
        $content = new MessageContent("  \n ");
        $this->assertTrue($content->isEmpty());
        $this->assertEquals("", $content->getValue());
    }

    public function test_it_is_not_empty_when_it_has_content(): void
    {
        $content = new MessageContent("content");
        $this->assertFalse($content->isEmpty());
    }
}
