<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Google\CloudFunctions\FunctionsFramework;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use CloudEvents\V1\CloudEventInterface;
use App\Infrastructure\DependencyInjection\Container;

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

    $triggerService = $container->createTriggerApplicationService();
    $triggerService->executeTriggers();

    $logger->log("Finished.");
}
