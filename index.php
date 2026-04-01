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
    $logger->logSplitter();
    $logger->log("headers: " . json_encode($request->getHeaders()));
    $body = $request->getBody()->getContents();
    $logger->log("body length: " . strlen($body));

    $isLocal = CloudFunctionUtils::isLocalHttp($request);
    $logger->log("Running as " . ($isLocal ? "local" : "cloud") . " mode");

    $container = new Container($isLocal);

    // Routing for Config Editor
    $appPath = CloudFunctionUtils::getFunctionName();
    $basePath = $isLocal ? '' : '/' . $appPath;
    $path = $request->getUri()->getPath();

    // GCF might strip the function name from the path depending on how it's called.
    // Try to match both with and without the function name prefix.
    $subPath = null;
    $matchedBasePath = '';
    if (str_starts_with($path, $basePath . '/config')) {
        $subPath = substr($path, strlen($basePath . '/config'));
        $matchedBasePath = $basePath;
    } elseif (str_starts_with($path, '/config')) {
        $subPath = substr($path, strlen('/config'));
        $matchedBasePath = '';
    }

    if ($subPath !== null) {
        $configService = $container->createConfigApplicationService();
        $configService->setBasePath($matchedBasePath);

        if ($subPath === '' || $subPath === '/') {
            return new Response(200, ['Content-Type' => 'text/html'], $configService->renderIndex());
        }
        if ($subPath === '/edit') {
            $botId = $request->getQueryParams()['bot_id'] ?? null;
            return new Response(200, ['Content-Type' => 'text/html'], $configService->renderEdit($botId));
        }
        if ($subPath === '/save') {
            $params = $request->getParsedBody();
            if (empty($params)) {
                parse_str($body, $params);
            }
            $configService->saveBotConfig((string)$params['bot_id'], (string)$params['json_content']);
            return new Response(302, ['Location' => $matchedBasePath . '/config/edit?bot_id=' . $params['bot_id']]);
        }
        if ($subPath === '/delete') {
            $params = $request->getParsedBody();
            if (empty($params)) {
                parse_str($body, $params);
            }
            $configService->deleteBot((string)$params['bot_id']);
            return new Response(302, ['Location' => $matchedBasePath . '/config']);
        }
        if ($subPath === '/trigger/save') {
            $params = $request->getParsedBody();
            if (empty($params)) {
                parse_str($body, $params);
            }
            $triggerId = $params['trigger_id'] ?: uniqid('trigger_');
            $configService->saveTrigger((string)$params['bot_id'], (string)$triggerId, (string)$params['trigger_json']);
            return new Response(302, ['Location' => $matchedBasePath . '/config/edit?bot_id=' . $params['bot_id']]);
        }
        if ($subPath === '/trigger/delete') {
            $params = $request->getParsedBody();
            if (empty($params)) {
                parse_str($body, $params);
            }
            $configService->deleteTrigger((string)$params['bot_id'], (string)$params['trigger_id']);
            return new Response(302, ['Location' => $matchedBasePath . '/config/edit?bot_id=' . $params['bot_id']]);
        }
    }

    if (empty($body)) {
        return new Response(200, ['Content-Type' => 'text/plain'], 'OK');
    }

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
    $isLocal = CloudFunctionUtils::isLocalEvent($event);
    $logger->log("Running as " . ($isLocal ? "local" : "cloud") . " mode");

    $container = new Container($isLocal);
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
