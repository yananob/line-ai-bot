<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Line;

use App\Infrastructure\Line\LineClient;
use LINE\Clients\MessagingApi\Api\MessagingApiApi;
use LINE\Clients\MessagingApi\Model\PushMessageRequest;
use LINE\Clients\MessagingApi\Model\ReplyMessageRequest;
use LINE\Clients\MessagingApi\Model\ShowLoadingAnimationRequest;
use LINE\Clients\MessagingApi\Model\TextMessage;
use PHPUnit\Framework\TestCase;
use Exception;

final class LineClientTest extends TestCase
{
    private array $tokens = ['bot1' => 'token1'];
    private array $targets = ['target1' => 'id1'];

    public function test_sendPush_calls_messaging_api_with_correct_parameters(): void
    {
        $apiMock = $this->createMock(MessagingApiApi::class);

        $apiMock->expects($this->once())
            ->method('pushMessage')
            ->with($this->callback(function (PushMessageRequest $request) {
                /** @var TextMessage $message */
                $message = $request->getMessages()[0];
                return $request->getTo() === 'id1' && $message->getText() === 'hello';
            }));

        $client = new class($this->tokens, $this->targets, $apiMock) extends LineClient {
            private $mockApi;
            public function __construct($tokens, $targets, $mockApi) {
                parent::__construct($tokens, $targets);
                $this->mockApi = $mockApi;
            }
            protected function getApi(string $bot): MessagingApiApi {
                return $this->mockApi;
            }
        };

        $client->sendPush('bot1', 'target1', null, 'hello');
    }

    public function test_sendReply_calls_messaging_api_with_correct_parameters(): void
    {
        $apiMock = $this->createMock(MessagingApiApi::class);

        $apiMock->expects($this->once())
            ->method('replyMessage')
            ->with($this->callback(function (ReplyMessageRequest $request) {
                /** @var TextMessage $message */
                $message = $request->getMessages()[0];
                return $request->getReplyToken() === 'token' && $message->getText() === 'reply';
            }));

        $client = new class($this->tokens, $this->targets, $apiMock) extends LineClient {
            private $mockApi;
            public function __construct($tokens, $targets, $mockApi) {
                parent::__construct($tokens, $targets);
                $this->mockApi = $mockApi;
            }
            protected function getApi(string $bot): MessagingApiApi {
                return $this->mockApi;
            }
        };

        $client->sendReply('bot1', 'token', 'reply');
    }

    public function test_sendReply_with_quickReply_calls_messaging_api_with_correct_parameters(): void
    {
        $apiMock = $this->createMock(MessagingApiApi::class);

        $quickReplyItems = [
            ['type' => 'action', 'action' => ['type' => 'message', 'label' => 'label', 'text' => 'text']]
        ];

        $apiMock->expects($this->once())
            ->method('replyMessage')
            ->with($this->callback(function (ReplyMessageRequest $request) use ($quickReplyItems) {
                /** @var TextMessage $message */
                $message = $request->getMessages()[0];
                $quickReply = $message->getQuickReply();
                return $request->getReplyToken() === 'token'
                    && $message->getText() === 'reply'
                    && $quickReply !== null
                    && count($quickReply->getItems()) === 1;
            }));

        $client = new class($this->tokens, $this->targets, $apiMock) extends LineClient {
            private $mockApi;
            public function __construct($tokens, $targets, $mockApi) {
                parent::__construct($tokens, $targets);
                $this->mockApi = $mockApi;
            }
            protected function getApi(string $bot): MessagingApiApi {
                return $this->mockApi;
            }
        };

        $client->sendReply('bot1', 'token', 'reply', $quickReplyItems);
    }

    public function test_showLoading_calls_messaging_api_with_correct_parameters(): void
    {
        $apiMock = $this->createMock(MessagingApiApi::class);

        $apiMock->expects($this->once())
            ->method('showLoadingAnimation')
            ->with($this->callback(function (ShowLoadingAnimationRequest $request) {
                return $request->getChatId() === 'id1' && $request->getLoadingSeconds() === 60;
            }));

        $client = new class($this->tokens, $this->targets, $apiMock) extends LineClient {
            private $mockApi;
            public function __construct($tokens, $targets, $mockApi) {
                parent::__construct($tokens, $targets);
                $this->mockApi = $mockApi;
            }
            protected function getApi(string $bot): MessagingApiApi {
                return $this->mockApi;
            }
        };

        $client->showLoading('bot1', 'target1');
    }

    public function test_resolveTarget_throws_exception_for_unknown_target(): void
    {
        $client = new LineClient($this->tokens, $this->targets);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Unknown target: unknown');

        $client->sendPush('bot1', 'unknown', null, 'hello');
    }

    public function test_resolveTarget_throws_exception_for_missing_target(): void
    {
        $client = new LineClient($this->tokens, $this->targets);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Please specify $target or $targetId');

        $client->sendPush('bot1', null, null, 'hello');
    }

    public function test_getTargets_returns_filtered_targets(): void
    {
        $targets = [
            'target1' => 'id1',
            '__hidden' => 'id2',
            'target2' => 'id3'
        ];
        $client = new LineClient($this->tokens, $targets);

        $this->assertSame(['target1', 'target2'], $client->getTargets());
    }
}
