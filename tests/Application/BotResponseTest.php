<?php

declare(strict_types=1);

namespace Tests\Application;

use App\Application\BotResponse;
use PHPUnit\Framework\TestCase;

final class BotResponseTest extends TestCase
{
    public function test_can_instantiate_with_text_only(): void
    {
        $response = new BotResponse('Hello world');
        $this->assertSame('Hello world', $response->getText());
        $this->assertNull($response->getQuickReply());
    }

    public function test_can_instantiate_with_quick_reply(): void
    {
        $quickReply = [['type' => 'action', 'action' => ['type' => 'message', 'label' => 'Yes', 'text' => 'yes']]];
        $response = new BotResponse('Choose an option', $quickReply);
        $this->assertSame('Choose an option', $response->getText());
        $this->assertSame($quickReply, $response->getQuickReply());
    }
}
