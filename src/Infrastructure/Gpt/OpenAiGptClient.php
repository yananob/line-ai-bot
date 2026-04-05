<?php

declare(strict_types=1);

namespace App\Infrastructure\Gpt;

use App\Domain\Bot\Service\GptInterface;
use OpenAI\Contracts\ClientContract;
use App\Infrastructure\Logger\Logger;

class OpenAiGptClient implements GptInterface
{
    private ClientContract $client;
    private string $model;
    private ?Logger $logger;

    public function __construct(ClientContract $client, string $model, ?Logger $logger = null)
    {
        $this->client = $client;
        $this->model = $model;
        $this->logger = $logger;
    }

    public function getAnswer(string $context, string $message, array $options = []): string
    {
        $payload = [
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $context,
                ],
                [
                    'role' => 'user',
                    'content' => $message,
                ],
            ],
        ];

        $payload = array_merge($payload, $options);

        if ($this->logger) {
            $this->logger->log("OpenAI Request Payload: " . json_encode($payload, JSON_UNESCAPED_UNICODE));
        }

        $response = $this->client->chat()->create($payload);
        $answer = $response->choices[0]->message->content;

        if ($this->logger) {
            $this->logger->log("OpenAI Response: " . $answer);
        }

        return $answer;
    }
}
