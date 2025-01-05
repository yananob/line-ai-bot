<?php

declare(strict_types=1);

namespace MyApp;

// TODO: MyToolsに移す
class LineWebhookMessage
{
    private $bodyObj;
    private $event;
    private string $type;

    const TYPE_MESSAGE = "message";
    const TYPE_POSTBACK = "postback";

    const CMD_REMOVE_TRIGGER = "delete_trigger";

    public function __construct(string $messageBody)
    {
        $this->bodyObj = json_decode($messageBody, false);
        $this->event = $this->bodyObj->events[0];
        $this->type = $this->event->type;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getMessage(): ?string
    {
        return $this->type === self::TYPE_MESSAGE ? $this->event->message->text : null;
    }

    public function getPostbackData(): ?string
    {
        return $this->type === self::TYPE_POSTBACK ? $this->event->postback->data : null;
    }

    public function getTargetId(): string
    {
        $type = $this->event->source->type;
        // typeを判定して、idを取得
        if ($type === 'user') {
            return $this->event->source->userId;
        } else if ($type === 'group') {
            return  $this->event->source->groupId;
        } else if ($type === 'room') {
            return $this->event->source->roomId;
        } else {
            throw new \Exception("Unknown type :" + $type);
        }
    }

    public function getReplyToken(): string
    {
        return $this->event->replyToken;
    }
}
