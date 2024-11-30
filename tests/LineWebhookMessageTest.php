<?php

declare(strict_types=1);

// require_once "vendor/autoload.php";

use yananob\mytools\Utils;
use MyApp\LineWebhookMessage;

final class LineWebhookMessageTest extends PHPUnit\Framework\TestCase
{
    const TEST_EVENT = <<<EOM
{
    "destination": "d4eb1d4beb26f7e26de2cbfc2d01fb51b",
    "events": [
        {
            "type": "message",
            "message": {
                "type": "text",
                "id": "037022103506649397",
                "quoteToken": "cdAmPhmRkFByVkQZqf-Dvt0Rshzg2WGRN46eQe36NjrRVd0ctmsAQCLc2xUhhzhwj8E3ShW14QuRnNoxE4lxY5ndpTC_YLE9vDv8hQypn5jAxGbkd55fl9erbSmZdukuTFHk6LjC-zvT6sLXBJOdEQ",
                "text": "今年のクリスマスは何月何日でしょうか？\\n昨年のクリスマスとは違うのでしょうか？"
            },
            "webhookEventId": "Z1JDX2MVS87CHJDGAG57P822DQ",
            "deliveryContext": {
                "isRedelivery": false
            },
            "timestamp": 1732921421368,
            "source": {
                "type": "group",
                "groupId": "Cz8ae3320b1b13dbdaff35ae50dc09500",
                "userId": "U56b4c873e7e93648c421114c1b4b09e8"
            },
            "replyToken": "b3c26b13dfc74f6387c8bea36965e27c",
            "mode": "active"
        }
    ]
}
EOM;

    private LineWebhookMessage $webhookMessage;

    protected function setUp(): void
    {
        $this->webhookMessage = new LineWebhookMessage(self::TEST_EVENT);
    }

    public function testGetMessage(): void
    {
        $this->assertSame("今年のクリスマスは何月何日でしょうか？\n昨年のクリスマスとは違うのでしょうか？", $this->webhookMessage->getMessage());
    }
}
