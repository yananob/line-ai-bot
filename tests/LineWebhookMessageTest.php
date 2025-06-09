<?php

declare(strict_types=1);

namespace MyApp\Tests; // 名前空間を追加

use MyApp\Consts;
use MyApp\LineWebhookMessage;
use PHPUnit\Framework\TestCase; // TestCaseをuse

final class LineWebhookMessageTest extends TestCase // TestCaseの完全修飾名を使用 (useしたのでこれでOK)
{
    // テスト用グループメッセージJSON
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

    // テスト用ユーザーメッセージJSON
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

    // テスト用ユーザーポストバックJSON
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

    public function test_タイプを取得する(): void
    {
        $this->assertSame("message", $this->groupMessage->getType());
        $this->assertSame("message", $this->userMessage->getType());
        $this->assertSame("postback", $this->userPostback->getType());
    }

    public function test_メッセージを取得する(): void
    {
        $this->assertSame("今年のクリスマスは何月何日でしょうか？\n昨年のクリスマスとは違うのでしょうか？", $this->groupMessage->getMessage());
    }

    public function test_ポストバックデータを取得する(): void
    {
        parse_str($this->userPostback->getPostbackData(), $params);
        $this->assertSame(Consts::CMD_REMOVE_TRIGGER, $params["command"]);
        $this->assertSame("123456", $params["id"]);
    }

    public function test_ターゲットIDを取得する(): void
    {
        $this->assertSame("Cz8ae3320b1b13dbdaff35ae50dc09500", $this->groupMessage->getTargetId());
        $this->assertSame("U56b4c873e7e93648c421114c1b4b09e8", $this->userMessage->getTargetId());
    }

    public function test_リプライトークンを取得する(): void
    {
        $this->assertSame("b3c26b13dfc74f6387c8bea36965e27c", $this->groupMessage->getReplyToken());
    }
}
