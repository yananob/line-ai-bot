<?php declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Google\CloudFunctions\FunctionsFramework;
use GuzzleHttp\Psr7\Response;
use yananob\mytools\Logger;
// use yananob\mytools\Utils;
use yananob\mytools\Line;
use MyApp\LineWebhookMessage;
use MyApp\PersonalConsultant;
use myapp\TargetNotDefinedException;

FunctionsFramework::http('main', 'main');
function main(ServerRequestInterface $request): ResponseInterface
{
    // $config = Utils::getConfig(__DIR__ . "/configs/config.json", asArray: false);

    $logger = new Logger("webhook-receive");
    $logger->log(str_repeat("-", 120));
    $logger->log("headers: " . json_encode($request->getHeaders()));
    // $logger->log("params: " . json_encode($request->getQueryParams()));
    // $logger->log("parsedBody: " . json_encode($request->getParsedBody()));
    $body = $request->getBody()->getContents();
    $logger->log("body: " . $body);

    /** 
     * 1. LINE webhook受ける
     * 2. LINE webhook処理クラスで、target特定する
     * 3. targetから、Consultantを生成
     * 4. Consultantからメッセージもらう
     * 5. メッセージをLINEで送る
     */

    $headers = ['Content-Type' => 'application/json'];

    $webhookMessage = new LineWebhookMessage($body);
    $line = new Line(__DIR__ . "/configs/line.json");
    try {
        $consultant = new PersonalConsultant(__DIR__ . "/configs/config.json", $webhookMessage->getTargetId());
        $line->sendMessage(
            bot: $consultant->getLineTarget(),
            targetId: $webhookMessage->getTargetId(),
            message: $consultant->getAnswer($webhookMessage->getMessage()),
            replyToken: $webhookMessage->getReplyToken(),
        );
    } catch (TargetNotDefinedException $e) {
        $logger->log("Non defined targetId: {$e}");
        return new Response(400, $headers, '{"result": "ng"');
    }

    return new Response(200, $headers, '{"result": "ok"}');
}
