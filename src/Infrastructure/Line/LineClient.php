<?php

declare(strict_types=1);

namespace App\Infrastructure\Line;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Exception;

class LineClient
{
    private Client $httpClient;
    private array $tokens;
    private array $presetTargetIds;

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

    /**
     * Sends a push message.
     */
    public function sendPush(
        string $bot,
        ?string $target = null,
        ?string $targetId = null,
        string $message = "",
    ): void {
        if (!array_key_exists($bot, $this->tokens)) {
            throw new Exception("Unknown bot: {$bot}");
        }
        if (!empty($target) && !array_key_exists($target, $this->presetTargetIds)) {
            throw new Exception("Unknown target: {$target}");
        }
        if (empty($target) && empty($targetId)) {
            throw new Exception('Please specify $target or $targetId');
        }

        $body = [
            "to" => empty($target) ? $targetId : $this->presetTargetIds[$target],
            "messages" => [
                [
                    "type" => "text",
                    "text" => $message,
                ],
            ],
        ];
        $this->callApi("https://api.line.me/v2/bot/message/push", $bot, $body);
    }

    /**
     * Sends a reply message.
     */
    public function sendReply(
        string $bot,
        string $replyToken,
        string $message,
        ?array $quickReply = null,
    ): void {
        if (!array_key_exists($bot, $this->tokens)) {
            throw new Exception("Unknown bot: {$bot}");
        }

        $body = [
            "replyToken" => $replyToken,
            "messages" => [
                [
                    "type" => "text",
                    "text" => $message,
                ],
            ],
        ];

        if (!empty($quickReply)) {
            $body["messages"][0]["quickReply"]["items"] = $quickReply;
        }

        $this->callApi("https://api.line.me/v2/bot/message/reply", $bot, $body);
    }

    /**
     * Shows a loading animation.
     */
    public function showLoading(
        string $bot,
        ?string $target = null,
        ?string $targetId = null,
    ): void {
        if (!empty($target) && !array_key_exists($target, $this->presetTargetIds)) {
            throw new Exception("Unknown target: {$target}");
        }
        if (empty($target) && empty($targetId)) {
            throw new Exception('Please specify $target or $targetId');
        }

        $body = [
            "chatId" => empty($target) ? $targetId : $this->presetTargetIds[$target],
            "loadingSeconds" => 60,
        ];

        try {
            $this->callApi("https://api.line.me/v2/bot/chat/loading/start", $bot, $body, [202]);
        } catch (Exception $e) {
            // Silently fail as per original implementation (might be a group/room)
        }
    }

    /**
     * Calls LINE API.
     */
    private function callApi(string $url, string $bot, array $body, array $allowHttpCodes = [200]): void
    {
        try {
            $response = $this->httpClient->post($url, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => "Bearer {$this->tokens[$bot]}",
                ],
                'json' => $body,
            ]);

            $statusCode = $response->getStatusCode();
            if (!in_array($statusCode, $allowHttpCodes)) {
                throw new Exception(
                    "Failed to send message [bot: {$bot}]. Http response code: [{$statusCode}]"
                );
            }
        } catch (GuzzleException $e) {
            throw new Exception("API call failed: " . $e->getMessage(), 0, $e);
        }
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
