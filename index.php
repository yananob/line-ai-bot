<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Carbon\Carbon;
use Google\CloudFunctions\FunctionsFramework;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use CloudEvents\V1\CloudEventInterface;
use GuzzleHttp\Psr7\Response;
use MyApp\BotConfigsStore;
use MyApp\Command;
use yananob\MyTools\Logger;
// use yananob\MyTools\Utils;
use yananob\MyTools\Line;
use yananob\MyGcpTools\CFUtils;
use MyApp\LineWebhookMessage;
use MyApp\PersonalBot;

const TIMER_TRIGGERED_BY_N_MINS = 30;

FunctionsFramework::http('main', 'main');
function main(ServerRequestInterface $request): ResponseInterface
{
    $logger = new Logger("line-ai-bot");
    $logger->log(str_repeat("-", 120));
    $logger->log("headers: " . json_encode($request->getHeaders()));
    // $logger->log("params: " . json_encode($request->getQueryParams()));
    // $logger->log("parsedBody: " . json_encode($request->getParsedBody()));
    $body = $request->getBody()->getContents();
    $logger->log("body: " . $body);

    $isLocal = CFUtils::isLocalHttp($request);
    $logger->log("Running as " . ($isLocal ? "local" : "cloud") . " mode");

    $headers = ['Content-Type' => 'application/json'];

    $logicBot = new LogicBot();
    $webhookMessage = new LineWebhookMessage($body);
    $bot = new PersonalBot(
        $webhookMessage->getTargetId(),
        $isLocal
    );

    $command = $logicBot->judgeCommand($webhookMessage->getMessage());
    $answer = "";
    switch ($command) {
        case Command::AddOneTimeTrigger:
            $trigger = $logicBot->splitOneTimeTrigger($webhookMessage->getMessage());
            $bot->addOneTimeTrigger($trigger);
            $answer = "トリガーを追加：" . var_export($trigger);  // TODO: メッセージに
            break;

        case Command::AddDaiyTrigger:
            # code...
            break;

        default:
            $answer = $bot->getAnswer(
                applyRecentConversations: true,
                message: $webhookMessage->getMessage(),
            );
            $bot->storeConversations(
                message: $webhookMessage->getMessage(),
                answer: $answer,
            );
            break;
    }

    $line = new Line(__DIR__ . "/configs/line.json");
    $line->sendReply(
        bot: $bot->getLineTarget(),
        message: $answer,
        replyToken: $webhookMessage->getReplyToken(),
        targetId: $webhookMessage->getTargetId(),
    );

    return new Response(200, $headers, '{"result": "ok"}');
}

FunctionsFramework::cloudEvent('trigger', 'trigger');
function trigger(CloudEventInterface $event): void
{
    $logger = new Logger("line-ai-bot");
    $logger->log(str_repeat("-", 120));
    $isLocal = CFUtils::isLocalEvent($event);
    $logger->log("Running as " . ($isLocal ? "local" : "cloud") . " mode");

    $line = new Line(__DIR__ . "/configs/line.json");
    $botConfigStore = new BotConfigsStore($isLocal);
    foreach ($botConfigStore->getUsers() as $user) {
        foreach ($user->getTriggers() as $trigger) {
            $logger->log("user: {$user->getId()}, trigger: {$trigger->event} {$trigger->time}");
            if ($trigger->event !== "timer") {
                continue;
            }

            $triggerDate = $trigger->date;
            if ($triggerDate === "everyday") {
                $triggerDate = "today";
            }
            $triggerTime = new Carbon($triggerDate . " " . $trigger->time, new DateTimeZone("Asia/Tokyo"));
            $now = new Carbon(timezone: new DateTimeZone("Asia/Tokyo"));
            // $logger->log($triggerTime);
            // $logger->log($now);
            // $logger->log($triggerTime->diffInMinutes($now));
            if (($triggerTime->diffInMinutes($now) > TIMER_TRIGGERED_BY_N_MINS) || ($triggerTime->diffInMinutes($now) < 0)) {
                continue;
            }

            $bot = new PersonalBot($user->getId(), $isLocal);
            $answer =  $bot->askRequest(
                applyRecentConversations: true,
                request: $trigger->request
            );
            $line->sendPush(
                bot: $bot->getLineTarget(),
                targetId: $user->getId(),
                message: $answer,
            );
        }
    }

    $logger->log("Finished.");
}
