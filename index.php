<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Google\CloudFunctions\FunctionsFramework;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use CloudEvents\V1\CloudEventInterface;
use App\Domain\Bot\Trigger\TimerTrigger;
use App\Infrastructure\DependencyInjection\Container;

const TIMER_TRIGGERED_BY_N_MINS = 10;

FunctionsFramework::http('main_http', 'main_http');
function main_http(ServerRequestInterface $request): ResponseInterface
{
    $container = new Container();
    $path = $request->getUri()->getPath();

    // Routing for Config Editor
    if (stripos($path, '/config') !== false) {
        return $container->createBotConfigController()->handle($request);
    }

    // Default: LINE Webhook
    return $container->createLineWebhookController()->handle($request);
}

FunctionsFramework::cloudEvent('main_event', 'main_event');
function main_event(CloudEventInterface $event): void
{
    $container = new Container();
    $logger = $container->getLogger();
    $logger->logSplitter();
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

            $logger->log(sprintf(
                "Executing Trigger for bot %s: Date=%s, Time=%s, Request=%s",
                $botUser->getId(),
                $trigger->getDate(),
                $trigger->getTime(),
                $trigger->getRequest()
            ));

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
