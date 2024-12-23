<?php declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Google\CloudFunctions\FunctionsFramework;
use GuzzleHttp\Psr7\Response;
use yananob\MyTools\Logger;
// use yananob\MyTools\Utils;
use yananob\MyTools\Line;
use yananob\MyGcpTools\CFUtils;
use MyApp\LineWebhookMessage;
use MyApp\PersonalBot;

FunctionsFramework::http('main', 'main');
function main(ServerRequestInterface $request): ResponseInterface
{
    $logger = new Logger("webhook-receive");
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
    $line->sendMessage(
        bot: $consultant->getLineTarget(),
        targetId: $webhookMessage->getTargetId(),
        message: $answer,
        replyToken: $webhookMessage->getReplyToken(),
    );

    return new Response(200, $headers, '{"result": "ok"}');
}
