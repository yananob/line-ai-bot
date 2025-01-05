<?php

declare(strict_types=1);

use MyApp\Consts;
use MyApp\LineWebhookMessage;

final class LineWebhookMessageTest extends PHPUnit\Framework\TestCase
{
    const TEST_GROUP_MESSAGE = <<<EOM
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

    const TEST_USER_MESSAGE = <<<EOM
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
                "type": "user",
                "userId": "U56b4c873e7e93648c421114c1b4b09e8"
            },
            "replyToken": "b3c26b13dfc74f6387c8bea36965e27c",
            "mode": "active"
        }
    ]
}
EOM;

    const TEST_USER_POSTBACK = <<<EOM
{
    "destination": "z4eb1d4beb26f7e26de2cbfc2d01fb51b",
    "events": [
        {
            "type": "postback",
            "postback": {
                "data": "command=delete_trigger&id=123456"
            },
            "webhookEventId": "z1JGTBYA895B3A54G3JRA1AVGY",
            "deliveryContext": {
                "isRedelivery": false
            },
            "timestamp": 1736051730195,
            "source": {
                "type": "user",
                "userId": "U45b4c873e7e93648c421114c1b4b09e8"
            },
            "replyToken": "23bc4b6aa81f4995b1ca40f9cd2c658e",
            "mode": "active"
        }
    ]
}
EOM;

    private LineWebhookMessage $groupMessage;
    private LineWebhookMessage $userMessage;
    private LineWebhookMessage $userPostback;

    protected function setUp(): void
    {
        $this->groupMessage = new LineWebhookMessage(self::TEST_GROUP_MESSAGE);
        $this->userMessage = new LineWebhookMessage(self::TEST_USER_MESSAGE);
        $this->userPostback = new LineWebhookMessage(self::TEST_USER_POSTBACK);
    }

    public function testGetType(): void
    {
        $this->assertSame("message", $this->groupMessage->getType());
        $this->assertSame("message", $this->userMessage->getType());
        $this->assertSame("postback", $this->userPostback->getType());
    }

    public function testGetMessage(): void
    {
        $this->assertSame("今年のクリスマスは何月何日でしょうか？\n昨年のクリスマスとは違うのでしょうか？", $this->groupMessage->getMessage());
    }

    public function testGetPostbackData(): void
    {
        parse_str($this->userPostback->getPostbackData(), $params);
        $this->assertSame(Consts::CMD_REMOVE_TRIGGER, $params["command"]);
        $this->assertSame("123456", $params["id"]);
    }

    public function testGetTargetId(): void
    {
        $this->assertSame("Cz8ae3320b1b13dbdaff35ae50dc09500", $this->groupMessage->getTargetId());
        $this->assertSame("U56b4c873e7e93648c421114c1b4b09e8", $this->userMessage->getTargetId());
    }

    public function testGetReplyToken(): void
    {
        $this->assertSame("b3c26b13dfc74f6387c8bea36965e27c", $this->groupMessage->getReplyToken());
    }
}
