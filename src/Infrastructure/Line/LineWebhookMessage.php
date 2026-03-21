<?php

declare(strict_types=1);

namespace App\Infrastructure\Line;

use App\Domain\Exception\InvalidWebhookEventException;
use LINE\Webhook\Model\Event;
use LINE\Webhook\Model\MessageEvent;
use LINE\Webhook\Model\PostbackEvent;
use LINE\Webhook\Model\TextMessageContent;
use LINE\Webhook\Model\MessageContent;
use LINE\Webhook\Model\PostbackContent;
use LINE\Webhook\Model\Source;
use LINE\Webhook\Model\UserSource;
use LINE\Webhook\Model\GroupSource;
use LINE\Webhook\Model\RoomSource;
use LINE\Webhook\ObjectSerializer;

class LineWebhookMessage
{
    private Event $event;

    const TYPE_MESSAGE = "message";
    const TYPE_POSTBACK = "postback";

    public function __construct(string $messageBody)
    {
        $parsedBody = json_decode($messageBody, true);
        if (!isset($parsedBody['events'][0])) {
            throw new InvalidWebhookEventException("No events found in webhook body");
        }

        $this->event = $this->deserializeEvent($parsedBody['events'][0]);
    }

    private function deserializeEvent(array $data): Event
    {
        $type = $data['type'] ?? '';
        $class = match ($type) {
            'message' => MessageEvent::class,
            'postback' => PostbackEvent::class,
            default => Event::class,
        };

        /** @var Event $event */
        $event = ObjectSerializer::deserialize($data, $class);

        if (isset($data['source'])) {
            $sourceType = $data['source']['type'] ?? '';
            $sourceClass = match ($sourceType) {
                'user' => UserSource::class,
                'group' => GroupSource::class,
                'room' => RoomSource::class,
                default => Source::class,
            };
            $event->setSource(ObjectSerializer::deserialize($data['source'], $sourceClass));
        }

        if ($event instanceof MessageEvent && isset($data['message'])) {
            $messageType = $data['message']['type'] ?? '';
            $messageClass = match ($messageType) {
                'text' => TextMessageContent::class,
                default => MessageContent::class,
            };
            $event->setMessage(ObjectSerializer::deserialize($data['message'], $messageClass));
        }

        if ($event instanceof PostbackEvent && isset($data['postback'])) {
            $event->setPostback(ObjectSerializer::deserialize($data['postback'], PostbackContent::class));
        }

        return $event;
    }

    public function getType(): string
    {
        return $this->event->getType();
    }

    public function getMessage(): ?string
    {
        if ($this->event instanceof MessageEvent) {
            $content = $this->event->getMessage();
            if ($content instanceof TextMessageContent) {
                return $content->getText();
            }
        }
        return null;
    }

    public function getPostbackData(): ?string
    {
        if ($this->event instanceof PostbackEvent) {
            return $this->event->getPostback()->getData();
        }
        return null;
    }

    public function getTargetId(): string
    {
        $source = $this->event->getSource();
        if ($source instanceof UserSource) {
            return $source->getUserId();
        } elseif ($source instanceof GroupSource) {
            return $source->getGroupId();
        } elseif ($source instanceof RoomSource) {
            return $source->getRoomId();
        } else {
            throw new InvalidWebhookEventException("Unknown type :" . ($source ? $source->getType() : 'null'));
        }
    }

    public function getReplyToken(): string
    {
        if (method_exists($this->event, 'getReplyToken')) {
            return (string)$this->event->getReplyToken();
        }
        return "";
    }
}
