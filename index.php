<?php declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Carbon\Carbon;
use Google\CloudFunctions\FunctionsFramework;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use CloudEvents\V1\CloudEventInterface;
use GuzzleHttp\Psr7\Response;
use MyApp\BotConfigsStore;
use yananob\MyTools\Logger;
// use yananob\MyTools\Utils;
use yananob\MyTools\Line;
use yananob\MyGcpTools\CFUtils;
use MyApp\LineWebhookMessage;
use MyApp\PersonalBot;

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

    /** 
     * 1. LINE webhook受ける
     * 2. LINE webhook処理クラスで、target特定する
     * 3. targetから、Consultantを生成
     * 4. Consultantからメッセージもらう
     * 5. メッセージをLINEで送る
     */

    $headers = ['Content-Type' => 'application/json'];

    $webhookMessage = new LineWebhookMessage($body);
    $consultant = new PersonalBot(
        $webhookMessage->getTargetId(),
        $isLocal
    );
    $answer = $consultant->getAnswer(
        applyRecentConversations: true,
        message: $webhookMessage->getMessage(),
    );
    $consultant->storeConversations(
        message: $webhookMessage->getMessage(),
        answer: $answer,
    );

    $line = new Line(__DIR__ . "/configs/line.json");
    $line->sendReply(
        bot: $consultant->getLineTarget(),
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
            if ($trigger->getEvent() !== "timer") {
                continue;
            }

            $triggerTime = new Carbon($trigger->time);
            $now = new Carbon();
            if ($now->diffInMinutes($triggerTime) > 10) {
                continue;
            }

            $consultant = new PersonalBot($user->getTargetId(), $isLocal);
            $answer =  $consultant->getAnswer(
                applyRecentConversations: true,
                message: $trigger->getRequest()
            );
            $line->sendPush(
                bot: $consultant->getLineTarget(),
                targetId: $user->getTargetId(),
                message: $answer,
            );
        }
    }

    $logger->log("Finished.");
}
