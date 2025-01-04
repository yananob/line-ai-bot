<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Carbon\Carbon;
use Google\CloudFunctions\FunctionsFramework;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use CloudEvents\V1\CloudEventInterface;
use GuzzleHttp\Psr7\Response;
use yananob\MyTools\Logger;
// use yananob\MyTools\Utils;
use yananob\MyTools\Line;
use yananob\MyGcpTools\CFUtils;
use MyApp\Consts;
use MyApp\Command;
use MyApp\LineWebhookMessage;
use MyApp\BotConfigsStore;
use MyApp\PersonalBot;
use MyApp\LogicBot;

const TIMER_TRIGGERED_BY_N_MINS = 30;

FunctionsFramework::http('main', 'main');
function main(ServerRequestInterface $request): ResponseInterface
{
    $logger = new Logger("line-ai-bot");
    $logger->logSplitter();
    $logger->log("headers: " . json_encode($request->getHeaders()));
    // $logger->log("params: " . json_encode($request->getQueryParams()));
    // $logger->log("parsedBody: " . json_encode($request->getParsedBody()));
    $body = $request->getBody()->getContents();
    $logger->log("body: " . $body);

    $isLocal = CFUtils::isLocalHttp($request);
    $logger->log("Running as " . ($isLocal ? "local" : "cloud") . " mode");

    $headers = ['Content-Type' => 'application/json'];

    $webhookMessage = new LineWebhookMessage($body);
    $personalBot = new PersonalBot(
        $webhookMessage->getTargetId(),
        $isLocal
    );
    $line = new Line(__DIR__ . "/configs/line.json");
    $line->showLoading(
        bot: $personalBot->getLineTarget(),
        targetId: $webhookMessage->getTargetId(),
    );

    $logicBot = new LogicBot();
    $command = $logicBot->judgeCommand($webhookMessage->getMessage());
    $answer = "";
    switch ($command) {
        case Command::AddOneTimeTrigger:
            $trigger = $logicBot->generateOneTimeTrigger($webhookMessage->getMessage());
            $personalBot->addOneTimeTrigger($trigger);
            $answer = "追加しました：" . $trigger;  // TODO: メッセージに
            break;

        case Command::AddDaiyTrigger:
            $trigger = $logicBot->generateDailyTrigger($webhookMessage->getMessage());
            $personalBot->addDailyTrigger($trigger);
            $answer = "追加しました：" . $trigger;  // TODO: メッセージに
            break;

        default:
            $answer = $personalBot->getAnswer(
                applyRecentConversations: true,
                message: $webhookMessage->getMessage(),
            );
            $personalBot->storeConversations(
                message: $webhookMessage->getMessage(),
                answer: $answer,
            );
            break;
    }

    $line->sendReply(
        bot: $personalBot->getLineTarget(),
        message: $answer,
        replyToken: $webhookMessage->getReplyToken(),
    );

    return new Response(200, $headers, '{"result": "ok"}');
}

FunctionsFramework::cloudEvent('trigger', 'trigger');
function trigger(CloudEventInterface $event): void
{
    $logger = new Logger("line-ai-bot");
    $logger->logSplitter();
    $isLocal = CFUtils::isLocalEvent($event);
    $logger->log("Running as " . ($isLocal ? "local" : "cloud") . " mode");

    $line = new Line(__DIR__ . "/configs/line.json");
    $botConfigStore = new BotConfigsStore($isLocal);
    foreach ($botConfigStore->getUsers() as $user) {
        foreach ($user->getTriggers() as $trigger) {
            $logger->log("user: {$user->getId()}, trigger: {$trigger->getEvent()} {$trigger->getDate()} {$trigger->getTime()}");
            if ($trigger->getEvent() !== "timer") {
                continue;
            }

            // TODO: $trigger->shouldRun()

            $triggerDate = $trigger->getDate();
            if ($triggerDate === "everyday") {
                $triggerDate = "today";
            }
            $triggerTime = new Carbon($triggerDate . " " . $trigger->getTime(), new DateTimeZone(Consts::TIMEZONE));
            $now = new Carbon(timezone: new DateTimeZone(Consts::TIMEZONE));
            // $logger->log($triggerTime);
            // $logger->log($now);
            // $logger->log($triggerTime->diffInMinutes($now));
            if (($triggerTime->diffInMinutes($now) > TIMER_TRIGGERED_BY_N_MINS) || ($triggerTime->diffInMinutes($now) < 0)) {
                continue;
            }

            $personalBot = new PersonalBot($user->getId(), $isLocal);
            $answer =  $personalBot->askRequest(
                applyRecentConversations: true,
                request: $trigger->getRequest()
            );
            $line->sendPush(
                bot: $personalBot->getLineTarget(),
                targetId: $user->getId(),
                message: $answer,
            );
        }
    }

    $logger->log("Finished.");
}
