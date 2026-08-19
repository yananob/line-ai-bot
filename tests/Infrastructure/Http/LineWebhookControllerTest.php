<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Http;

use App\Domain\Bot\Bot;
use App\Domain\Bot\BotRepository;
use App\Domain\Exception\InvalidWebhookEventException;
use App\Infrastructure\DependencyInjection\Container;
use App\Infrastructure\Http\LineWebhookController;
use App\Infrastructure\Line\LineClient;
use App\Infrastructure\Logger\Logger;
use App\Application\ChatApplicationService;
use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

final class LineWebhookControllerTest extends TestCase
{
    private $botRepositoryMock;
    private $lineClientMock;
    private $loggerMock;
    private $containerMock;
    private LineWebhookController $controller;

    protected function setUp(): void
    {
        $this->botRepositoryMock = $this->createMock(BotRepository::class);
        $this->lineClientMock = $this->createMock(LineClient::class);
        $this->loggerMock = $this->createMock(Logger::class);
        $this->containerMock = $this->createMock(Container::class);

        $this->controller = new LineWebhookController(
            $this->botRepositoryMock,
            $this->lineClientMock,
            $this->loggerMock,
            $this->containerMock
        );
    }

    public function test_非POSTリクエストや空リボディに対してOKを返す(): void
    {
        $request = new ServerRequest('GET', '/');
        $response = $this->controller->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OK', (string)$response->getBody());
    }

    public function test_メッセージタイプが非対応の場合にInvalidWebhookEventExceptionをスローする(): void
    {
        $json = json_encode([
            'events' => [
                [
                    'type' => 'unsupported_type',
                    'source' => [
                        'type' => 'user',
                        'userId' => 'user123'
                    ],
                    'replyToken' => 'token123'
                ]
            ]
        ]);

        $request = new ServerRequest('POST', '/', [], $json);

        $bot = new Bot('user123');
        $chatServiceMock = $this->createMock(ChatApplicationService::class);
        $chatServiceMock->method('getLineTarget')->willReturn('test');

        $this->botRepositoryMock->expects($this->once())
            ->method('findOrDefault')
            ->with('user123')
            ->willReturn($bot);

        $this->containerMock->expects($this->once())
            ->method('createChatApplicationService')
            ->with($bot)
            ->willReturn($chatServiceMock);

        $this->expectException(InvalidWebhookEventException::class);
        $this->controller->handle($request);
    }
}
