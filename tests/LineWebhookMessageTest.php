<?php

declare(strict_types=1);

// require_once "vendor/autoload.php";

use yananob\mytools\Utils;
use MyApp\LineWebhookMessage;

final class LineWebhookMessageTest extends PHPUnit\Framework\TestCase
{
    const TEST_EVENT = <<<EOM
EOM;

    private LineWebhookMessage $webhookMessage;

    protected function setUp(): void
    {
        $this->webhookMessage = new LineWebhookMessage(self::TEST_EVENT);
    }

    public function testInit(): void
    {
        $this->assertTrue(true);
    }
}
