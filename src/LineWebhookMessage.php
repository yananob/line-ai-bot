<?php

declare(strict_types=1);

namespace MyApp;

class LineWebhookMessage
{
    private $bodyObj;
    private $event;

    public function __construct(private string $messageBody)
    {
        $this->bodyObj = json_decode($messageBody, false);
        $this->event = $this->bodyObj->events[0];
    }

    public function getMessage(): string
    {
        return $this->event->message->text;
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
