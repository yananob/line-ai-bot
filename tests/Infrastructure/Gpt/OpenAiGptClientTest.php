<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Gpt;

use App\Infrastructure\Gpt\OpenAiGptClient;
use OpenAI\Contracts\ClientContract;
use OpenAI\Contracts\Resources\ChatContract;
use OpenAI\Responses\Chat\CreateResponse;
use OpenAI\Responses\Meta\MetaInformation;
use PHPUnit\Framework\TestCase;

final class OpenAiGptClientTest extends TestCase
{
    public function test_getAnswer_calls_openai_with_correct_payload(): void
    {
        $clientMock = $this->createMock(ClientContract::class);
        $chatMock = $this->createMock(ChatContract::class);

        $clientMock->method('chat')->willReturn($chatMock);

        $expectedPayload = [
            'model' => 'gpt-4o',
            'messages' => [
                ['role' => 'system', 'content' => 'system context'],
                ['role' => 'user', 'content' => 'user message'],
            ],
            'temperature' => 0.7,
        ];

        // We can't easily instantiate CreateResponse directly with mock data without complex setup,
        // but we can mock it or use from() if it has it.
        // Actually, CreateResponse::from() exists in the library.
        $responseData = [
            'id' => 'chatcmpl-123',
            'object' => 'chat.completion',
            'created' => 1677652288,
            'model' => 'gpt-4o',
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'AI response',
                        'function_call' => null,
                        'tool_calls' => null,
                    ],
                    'logprobs' => null,
                    'finish_reason' => 'stop',
                ],
            ],
            'usage' => [
                'prompt_tokens' => 9,
                'completion_tokens' => 12,
                'total_tokens' => 21,
            ],
        ];
        /** @var array{x-request-id: string[], openai-model: string[], openai-organization: string[], openai-version: string[], openai-processing-ms: string[], x-ratelimit-limit-requests: string[], x-ratelimit-remaining-requests: string[], x-ratelimit-reset-requests: string[], x-ratelimit-limit-tokens: string[], x-ratelimit-remaining-tokens: string[], x-ratelimit-reset-tokens: string[]} $headers */
        $headers = [
            'x-request-id' => ['req_123'],
            'openai-model' => ['gpt-4o'],
            'openai-organization' => ['org-123'],
            'openai-version' => ['2020-10-01'],
            'openai-processing-ms' => ['100'],
            'x-ratelimit-limit-requests' => ['100'],
            'x-ratelimit-remaining-requests' => ['99'],
            'x-ratelimit-reset-requests' => ['1s'],
            'x-ratelimit-limit-tokens' => ['1000'],
            'x-ratelimit-remaining-tokens' => ['999'],
            'x-ratelimit-reset-tokens' => ['1s'],
        ];
        $meta = MetaInformation::from($headers);
        $response = CreateResponse::from($responseData, $meta);

        $chatMock->expects($this->once())
            ->method('create')
            ->with($expectedPayload)
            ->willReturn($response);

        $gptClient = new OpenAiGptClient($clientMock, 'gpt-4o');
        $answer = $gptClient->getAnswer('system context', 'user message', ['temperature' => 0.7]);

        $this->assertSame('AI response', $answer);
    }
}
