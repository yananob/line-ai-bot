<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Domain\Bot\BotRepository;
use App\Infrastructure\Line\LineClient;
use App\Infrastructure\Line\LineWebhookMessage;
use App\Infrastructure\Logger\Logger;
use App\Infrastructure\DependencyInjection\Container;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class LineWebhookController
{
    public function __construct(
        private BotRepository $botRepository,
        private LineClient $lineClient,
        private Logger $logger,
        private Container $container
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $body = (string)$request->getBody();
        if ($request->getMethod() !== 'POST' || trim($body) === '') {
            return new Response(200, ['Content-Type' => 'text/plain'], 'OK');
        }

        $this->logger->log("Received HTTP Webhook Body: " . $body);
        $webhookMessage = new LineWebhookMessage($body);

        try {
            $bot = $this->botRepository->findOrDefault($webhookMessage->getTargetId());
            $chatService = $this->container->createChatApplicationService($bot);
        } catch (\Exception $e) {
            $this->logger->log("Failed to initialize ChatApplicationService for target {$webhookMessage->getTargetId()}: " . $e->getMessage());
            return new Response(500, ['Content-Type' => 'application/json'], '{"result": "error", "message": "Bot initialization failed."}');
        }

        $this->lineClient->showLoading(
            bot: $chatService->getLineTarget(),
            targetId: $webhookMessage->getTargetId(),
        );

        if ($webhookMessage->getType() === LineWebhookMessage::TYPE_MESSAGE) {
            $botResponse = $chatService->handleMessage($webhookMessage->getMessage());
        } elseif ($webhookMessage->getType() === LineWebhookMessage::TYPE_POSTBACK) {
            $botResponse = $chatService->handlePostback($webhookMessage->getPostbackData());
        } else {
            throw new \Exception("Unsupported message type: " . $webhookMessage->getType());
        }

        $this->lineClient->sendReply(
            bot: $chatService->getLineTarget(),
            message: $botResponse->getText(),
            replyToken: $webhookMessage->getReplyToken(),
            quickReplyItems: $botResponse->getQuickReply(),
        );

        return new Response(200, ['Content-Type' => 'application/json'], '{"result": "ok"}');
    }
}
