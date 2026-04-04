<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Google\CloudFunctions\FunctionsFramework;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use CloudEvents\V1\CloudEventInterface;
use GuzzleHttp\Psr7\Response;
use App\Infrastructure\Logger\Logger;
use App\Infrastructure\Gcp\CloudFunctionUtils;
use App\Infrastructure\Line\LineWebhookMessage;
use App\Domain\Bot\Trigger\TimerTrigger;
use App\Infrastructure\DependencyInjection\Container;

const TIMER_TRIGGERED_BY_N_MINS = 10;

FunctionsFramework::http('main_http', 'main_http');
function main_http(ServerRequestInterface $request): ResponseInterface
{
    $logger = new Logger(CloudFunctionUtils::getFunctionName());
    $path = $request->getUri()->getPath();

    // Routing for Config Editor
    // Detect "/config" regardless of service name prefix (GCF behavior varies).
    if (($configPos = stripos($path, '/config')) !== false) {
        $container = new Container();
        $configService = $container->createConfigApplicationService();

        $basePath = \App\AppConfig::getBasePath();
        $subPath = substr($path, $configPos + strlen('/config'));
        $configService->setBasePath($basePath);

        if ($subPath === '' || $subPath === '/') {
            return new Response(200, ['Content-Type' => 'text/html'], $configService->renderIndex());
        }
        if ($subPath === '/edit') {
            $botId = $request->getQueryParams()['bot_id'] ?? null;
            return new Response(200, ['Content-Type' => 'text/html'], $configService->renderEdit($botId));
        }
        if ($subPath === '/save') {
            $body = (string)$request->getBody();
            $params = $request->getParsedBody();
            if (empty($params)) {
                parse_str($body, $params);
            }
            $botId = (string)$params['bot_id'];
            $data = [
                'bot_characteristics' => array_filter(array_map('trim', (array)($params['bot_characteristics'] ?? [])), fn($v) => $v !== ''),
                'human_characteristics' => array_filter(array_map('trim', (array)($params['human_characteristics'] ?? [])), fn($v) => $v !== ''),
                'requests' => array_filter(array_map('trim', (array)($params['requests'] ?? [])), fn($v) => $v !== ''),
                'line_target' => (string)($params['line_target'] ?? ''),
            ];
            $configService->saveBotConfig($botId, $data);
            return new Response(302, ['Location' => $basePath . '/config/edit?bot_id=' . $botId]);
        }
        if ($subPath === '/delete') {
            $body = (string)$request->getBody();
            $params = $request->getParsedBody();
            if (empty($params)) {
                parse_str($body, $params);
            }
            $configService->deleteBot((string)$params['bot_id']);
            return new Response(302, ['Location' => $basePath . '/config']);
        }
        if ($subPath === '/triggers') {
            $botId = $request->getQueryParams()['bot_id'] ?? null;
            if (!$botId) {
                return new Response(302, ['Location' => $basePath . '/config']);
            }
            return new Response(200, ['Content-Type' => 'text/html'], $configService->renderTriggers($botId));
        }
        if ($subPath === '/trigger/edit') {
            $botId = $request->getQueryParams()['bot_id'] ?? null;
            $triggerId = $request->getQueryParams()['trigger_id'] ?? null;
            if (!$botId) {
                return new Response(302, ['Location' => $basePath . '/config']);
            }
            return new Response(200, ['Content-Type' => 'text/html'], $configService->renderTriggerEdit($botId, $triggerId));
        }
        if ($subPath === '/trigger/save') {
            $body = (string)$request->getBody();
            $params = $request->getParsedBody();
            if (empty($params)) {
                parse_str($body, $params);
            }
            $botId = (string)$params['bot_id'];
            $triggerId = $params['trigger_id'] ?: uniqid('trigger_');
            $data = [
                'event' => (string)($params['event'] ?? 'timer'),
                'date' => (string)($params['date'] ?? ''),
                'time' => (string)($params['time'] ?? ''),
                'request' => (string)($params['request'] ?? ''),
            ];
            $configService->saveTrigger($botId, $triggerId, $data);
            return new Response(302, ['Location' => $basePath . '/config/triggers?bot_id=' . $botId]);
        }
        if ($subPath === '/trigger/delete') {
            $body = (string)$request->getBody();
            $params = $request->getParsedBody();
            if (empty($params)) {
                parse_str($body, $params);
            }
            $botId = (string)$params['bot_id'];
            $configService->deleteTrigger($botId, (string)$params['trigger_id']);
            return new Response(302, ['Location' => $basePath . '/config/triggers?bot_id=' . $botId]);
        }

        return new Response(404, [], 'Not Found');
    }

    $body = (string)$request->getBody();
    if ($request->getMethod() !== 'POST' || trim($body) === '') {
        return new Response(200, ['Content-Type' => 'text/plain'], 'OK');
    }

    $container = new Container();
    $webhookMessage = new LineWebhookMessage($body);

    try {
        $bot = $container->getBotRepository()->findOrDefault($webhookMessage->getTargetId());
        $chatService = $container->createChatApplicationService($bot);
    } catch (\Exception $e) {
        $logger->log("Failed to initialize ChatApplicationService for target {$webhookMessage->getTargetId()}: " . $e->getMessage());
        return new Response(500, ['Content-Type' => 'application/json'], '{"result": "error", "message": "Bot initialization failed."}');
    }

    $line = $container->getLineClient();
    $line->showLoading(
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

    $line->sendReply(
        bot: $chatService->getLineTarget(),
        message: $botResponse->getText(),
        replyToken: $webhookMessage->getReplyToken(),
        quickReplyItems: $botResponse->getQuickReply(),
    );
        
    return new Response(200, ['Content-Type' => 'application/json'], '{"result": "ok"}');
}

FunctionsFramework::cloudEvent('main_event', 'main_event');
function main_event(CloudEventInterface $event): void
{
    $logger = new Logger(CloudFunctionUtils::getFunctionName());
    $logger->logSplitter();
    $container = new Container();
    $line = $container->getLineClient();
    $botRepository = $container->getBotRepository();

    foreach ($botRepository->getAllUserBots() as $botUser) {
        foreach ($botUser->getTriggers() as $trigger) {
            if (!$trigger instanceof TimerTrigger) {
                $logger->log("Skipping trigger for user {$botUser->getId()} as it's not a TimerTrigger.");
                continue;
            }
            
            if ($trigger->getEvent() !== "timer") {
                continue;
            }

            if (!$trigger->shouldRunNow(TIMER_TRIGGERED_BY_N_MINS)) {
                continue;
            }

            try {
                $chatService = $container->createChatApplicationService($botUser);
            } catch (\Exception $e) {
                $logger->log("TRIGGER: Failed to initialize ChatApplicationService for user {$botUser->getId()}: " . $e->getMessage());
                continue;
            }

            $answer = $chatService->handleTrigger($trigger)->getText();
            $line->sendPush(
                bot: $chatService->getLineTarget(),
                targetId: $botUser->getId(),
                message: $answer,
            );
        }
    }

    $logger->log("Finished.");
}
