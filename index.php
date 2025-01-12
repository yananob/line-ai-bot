<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Google\CloudFunctions\FunctionsFramework;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use CloudEvents\V1\CloudEventInterface;
use GuzzleHttp\Psr7\Response;
use yananob\MyTools\Logger;
use yananob\MyTools\Line;
use yananob\MyGcpTools\CFUtils;
use MyApp\Consts;
use MyApp\Command;
use MyApp\LineWebhookMessage;
use MyApp\BotConfigsStore;
use MyApp\PersonalBot;
use MyApp\LogicBot;
use MyApp\Messages;
use MyApp\Tools;

const TIMER_TRIGGERED_BY_N_MINS = 10;

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

    $answer = "";
    $quickReply = null;
    if ($webhookMessage->getType() === LineWebhookMessage::TYPE_MESSAGE) {
        $logicBot = new LogicBot();
        $command = $logicBot->judgeCommand($webhookMessage->getMessage());
        switch ($command) {
            case Command::ShowHelp:
                $answer = Messages::HELP;
                break;

            case Command::AddOneTimeTrigger:
                $trigger = $logicBot->generateOneTimeTrigger($webhookMessage->getMessage());
                $personalBot->addTimerTrigger($trigger);
                $answer = "タイマーを追加しました：" . $trigger;  // TODO: メッセージに
                break;

            case Command::AddDaiyTrigger:
                $trigger = $logicBot->generateDailyTrigger($webhookMessage->getMessage());
                $personalBot->addTimerTrigger($trigger);
                $answer = "タイマーを追加しました：" . $trigger;  // TODO: メッセージに
                break;

            case Command::RemoveTrigger:
                $answer = "どのタイマーを止めますか？";
                $quickReply = Tools::convertTriggersToQuickReply(Consts::CMD_REMOVE_TRIGGER, $personalBot->getTriggers());
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
    } elseif ($webhookMessage->getType() === LineWebhookMessage::TYPE_POSTBACK) {
        parse_str($webhookMessage->getPostbackData(), $params);
        switch ($params["command"]) {
            case Consts::CMD_REMOVE_TRIGGER:
                $personalBot->deleteTrigger($params["id"]);
                $answer = "削除しました：" . $params["trigger"];  // TODO: メッセージに
                break;

            default:
                throw new Exception("Unsupported command: " . $params["command"]);
        }
    } else {
        throw new Exception("Unsupported message type: " . $webhookMessage->getType());
    }

    $line->sendReply(
        bot: CFUtils::isTestingEnv() ? "test" : $personalBot->getLineTarget(),
        message: $answer,
        replyToken: $webhookMessage->getReplyToken(),
        quickReply: $quickReply,
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
            $logger->log("user: {$user->getId()}, trigger: {$trigger}");
            if ($trigger->getEvent() !== "timer") {
                continue;
            }
            if (!$trigger->shouldRunNow(TIMER_TRIGGERED_BY_N_MINS)) {
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
