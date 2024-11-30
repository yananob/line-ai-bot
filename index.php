<?php declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Google\CloudFunctions\FunctionsFramework;
use GuzzleHttp\Psr7\Response;
use yananob\mytools\Logger;
use yananob\mytools\Utils;
use yananob\mytools\Line;
use yananob\mytools\Gpt;

const GPT_CONTEXT = <<<EOM
<bot/characteristics>

カウンセリング相手の情報：
<human/characteristics>

依頼事項：
カウンセリング相手からのメッセージに対して、カウンセリング相手の特徴を一部反映して、ポジティブなフィードバックを、300〜500字ぐらいで返してください。
EOM;

function getContext($config): string
{
    $result = GPT_CONTEXT;
    $replaceSettings = [
        ["search" => "<bot/characteristics>", "replace" => $config->bot->characteristics],
        ["search" => "<human/characteristics>", "replace" => $config->human->characteristics],
    ];
    foreach ($replaceSettings as $replaceSetting) {
        $result = str_replace($replaceSetting["search"], $replaceSetting["replace"], $result);
    }
    return $result;
}

FunctionsFramework::http('main', 'main');
function main(ServerRequestInterface $request): ResponseInterface
{
    $logger = new Logger("webhook-receive");
    $logger->log(str_repeat("-", 120));
    $logger->log("headers: " . json_encode($request->getHeaders()));
    $logger->log("params: " . json_encode($request->getQueryParams()));
    $logger->log("parsedBody: " . json_encode($request->getParsedBody()));
    $body = $request->getBody()->getContents();
    $logger->log("body: " . $body);
    $body = json_decode($body, false);

    $event = $body->events[0];
    $message = $event->message->text;

    $config = Utils::getConfig(__DIR__ . "/configs/config.json", asArray: false);

    $gpt = new Gpt(__DIR__ . "/configs/gpt.json");
    $answer = $gpt->getAnswer(
        context: getContext($config),
        message: $message,
    );

    // TODO: LINE Webhookから来たデータを処理するラッパーがあったほうがよさそう
    $type = $event->source->type;
    $targetId = null;
    // typeを判定して、idを取得
    if ($type === 'user') {
        $targetId = $event->source->userId;
    } else if ($type === 'group') {
        $targetId = $event->source->groupId;
    } else if ($type === 'room') {
        $targetId = $event->source->roomId;
    } else {
        throw new Exception("Unknown type :" + $type);
    }

    $line = new Line(__DIR__ . "/configs/line.json");
    $line->sendMessage(
        bot: $config->bot->line_target,
        targetId: $targetId,
        message: $answer,
        replyToken: $event->replyToken
    );
  
    $headers = ['Content-Type' => 'application/json'];
    return new Response(200, $headers, json_encode($body));
}
