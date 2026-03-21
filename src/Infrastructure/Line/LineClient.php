<?php

declare(strict_types=1);

namespace App\Infrastructure\Line;

use LINE\Clients\MessagingApi\Api\MessagingApiApi;
use LINE\Clients\MessagingApi\Configuration;
use LINE\Clients\MessagingApi\Model\PushMessageRequest;
use LINE\Clients\MessagingApi\Model\ReplyMessageRequest;
use LINE\Clients\MessagingApi\Model\TextMessage;
use LINE\Clients\MessagingApi\Model\QuickReply;
use LINE\Clients\MessagingApi\Model\ShowLoadingAnimationRequest;
use GuzzleHttp\Client;
use Exception;

class LineClient
{
    private array $tokens;
    private array $presetTargetIds;
    private Client $httpClient;

    /**
     * @param array $tokens LINE API access tokens
     * @param array $presetTargetIds Preset target IDs
     */
    public function __construct(array $tokens, array $presetTargetIds)
    {
        $this->tokens = $tokens;
        $this->presetTargetIds = $presetTargetIds;
        $this->httpClient = new Client();
    }

    private function getApi(string $bot): MessagingApiApi
    {
        if (!isset($this->tokens[$bot])) {
            throw new Exception("Unknown bot: {$bot}");
        }
        $config = new Configuration();
        $config->setAccessToken($this->tokens[$bot]);
        return new MessagingApiApi($this->httpClient, $config);
    }

    /**
     * Sends a push message.
     */
    public function sendPush(
        string $bot,
        ?string $target = null,
        ?string $targetId = null,
        string $message = "",
    ): void {
        $to = $this->resolveTarget($target, $targetId);
        $api = $this->getApi($bot);

        $textMessage = new TextMessage(['type' => 'text', 'text' => $message]);
        $request = new PushMessageRequest([
            'to' => $to,
            'messages' => [$textMessage]
        ]);

        $api->pushMessage($request);
    }

    /**
     * Sends a reply message.
     */
    public function sendReply(
        string $bot,
        string $replyToken,
        string $message,
        ?array $quickReplyItems = null,
    ): void {
        $api = $this->getApi($bot);

        $textMessage = new TextMessage(['type' => 'text', 'text' => $message]);
        if (!empty($quickReplyItems)) {
            $quickReply = new QuickReply(['items' => $quickReplyItems]);
            $textMessage->setQuickReply($quickReply);
        }

        $request = new ReplyMessageRequest([
            'replyToken' => $replyToken,
            'messages' => [$textMessage]
        ]);

        $api->replyMessage($request);
    }

    /**
     * Shows a loading animation.
     */
    public function showLoading(
        string $bot,
        ?string $target = null,
        ?string $targetId = null,
    ): void {
        $chatId = $this->resolveTarget($target, $targetId);
        $api = $this->getApi($bot);

        $request = new ShowLoadingAnimationRequest([
            'chatId' => $chatId,
            'loadingSeconds' => 60
        ]);

        try {
            $api->showLoadingAnimation($request);
        } catch (Exception $e) {
            // Silently fail as per original implementation
        }
    }

    private function resolveTarget(?string $target, ?string $targetId): string
    {
        if (!empty($target)) {
            if (!isset($this->presetTargetIds[$target])) {
                throw new Exception("Unknown target: {$target}");
            }
            return $this->presetTargetIds[$target];
        }
        if (empty($targetId)) {
            throw new Exception('Please specify $target or $targetId');
        }
        return $targetId;
    }

    /**
     * Gets preset target IDs.
     */
    public function getTargets(): array
    {
        $result = [];
        foreach (array_keys($this->presetTargetIds) as $target) {
            if (str_starts_with((string)$target, "__")) {
                continue;
            }
            $result[] = $target;
        }
        return $result;
    }
}
