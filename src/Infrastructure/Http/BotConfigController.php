<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Application\Config\ConfigApplicationService;
use App\AppConfig;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class BotConfigController
{
    public function __construct(
        private ConfigApplicationService $configService
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $path = $request->getUri()->getPath();
        $configPos = stripos($path, '/config');
        if ($configPos === false) {
            return new Response(404, [], 'Not Found');
        }

        $basePath = AppConfig::getBasePath();
        $subPath = substr($path, $configPos + strlen('/config'));
        $this->configService->setBasePath($basePath);

        if ($subPath === '' || $subPath === '/') {
            return new Response(200, ['Content-Type' => 'text/html'], $this->configService->renderIndex());
        }
        if ($subPath === '/edit') {
            $botId = $request->getQueryParams()['bot_id'] ?? null;
            return new Response(200, ['Content-Type' => 'text/html'], $this->configService->renderEdit($botId));
        }
        if ($subPath === '/save') {
            $params = $this->getParams($request);
            $botId = $params['bot_id'] ?: uniqid('bot_');
            $data = [
                'bot_name' => (string)($params['bot_name'] ?? ''),
                'bot_characteristics' => array_filter(array_map('trim', (array)($params['bot_characteristics'] ?? [])), fn($v) => $v !== ''),
                'human_characteristics' => array_filter(array_map('trim', (array)($params['human_characteristics'] ?? [])), fn($v) => $v !== ''),
                'requests' => array_filter(array_map('trim', (array)($params['requests'] ?? [])), fn($v) => $v !== ''),
                'line_target' => (string)($params['line_target'] ?? ''),
            ];
            $this->configService->saveBotConfig($botId, $data);
            return new Response(302, ['Location' => $basePath . '/config/edit?bot_id=' . $botId]);
        }
        if ($subPath === '/delete') {
            $params = $this->getParams($request);
            $this->configService->deleteBot((string)$params['bot_id']);
            return new Response(302, ['Location' => $basePath . '/config']);
        }
        if ($subPath === '/triggers') {
            $botId = $request->getQueryParams()['bot_id'] ?? null;
            if (!$botId) {
                return new Response(302, ['Location' => $basePath . '/config']);
            }
            return new Response(200, ['Content-Type' => 'text/html'], $this->configService->renderTriggers($botId));
        }
        if ($subPath === '/trigger/edit') {
            $botId = $request->getQueryParams()['bot_id'] ?? null;
            $triggerId = $request->getQueryParams()['trigger_id'] ?? null;
            if (!$botId) {
                return new Response(302, ['Location' => $basePath . '/config']);
            }
            return new Response(200, ['Content-Type' => 'text/html'], $this->configService->renderTriggerEdit($botId, $triggerId));
        }
        if ($subPath === '/trigger/save') {
            $params = $this->getParams($request);
            $botId = (string)$params['bot_id'];
            $triggerId = $params['trigger_id'] ?: uniqid('trigger_');
            $data = [
                'event' => (string)($params['event'] ?? 'timer'),
                'date' => (string)($params['date'] ?? ''),
                'time' => (string)($params['time'] ?? ''),
                'request' => (string)($params['request'] ?? ''),
            ];
            $this->configService->saveTrigger($botId, $triggerId, $data);
            return new Response(302, ['Location' => $basePath . '/config/triggers?bot_id=' . $botId]);
        }
        if ($subPath === '/trigger/delete') {
            $params = $this->getParams($request);
            $botId = (string)$params['bot_id'];
            $this->configService->deleteTrigger($botId, (string)$params['trigger_id']);
            return new Response(302, ['Location' => $basePath . '/config/triggers?bot_id=' . $botId]);
        }

        return new Response(404, [], 'Not Found');
    }

    private function getParams(ServerRequestInterface $request): array
    {
        $params = $request->getParsedBody();
        if (empty($params)) {
            $body = (string)$request->getBody();
            parse_str($body, $params);
        }
        return (array)$params;
    }
}
