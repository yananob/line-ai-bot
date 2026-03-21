<?php

declare(strict_types=1);

namespace App\Infrastructure\Gpt;

use App\Domain\Bot\Service\GptInterface;
use OpenAI\Contracts\ClientContract;

class OpenAiGptClient implements GptInterface
{
    private ClientContract $client;
    private string $model;

    public function __construct(ClientContract $client, string $model)
    {
        $this->client = $client;
        $this->model = $model;
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

        $response = $this->client->chat()->create($payload);

        return $response->choices[0]->message->content;
    }
}
