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
use MyApp\Domain\Bot\Consts;
use MyApp\Domain\Bot\ValueObject\Command;
use MyApp\Infrastructure\Line\LineWebhookMessage;
use MyApp\Application\ChatApplicationService;
use MyApp\Domain\Bot\Service\ChatPromptService;
use MyApp\Domain\Bot\Service\CommandAndTriggerService;
use MyApp\Infrastructure\Persistence\Firestore\FirestoreBotRepository;
use MyApp\Infrastructure\Persistence\Firestore\FirestoreConversationRepository;
use MyApp\Domain\Bot\Trigger\TimerTrigger; // For type hinting if needed
use MyApp\Domain\Bot\Messages;
use MyApp\Infrastructure\Line\LineTools;

const TIMER_TRIGGERED_BY_N_MINS = 10;

FunctionsFramework::http('main_http', 'main_http');
function main_http(ServerRequestInterface $request): ResponseInterface
{
    $logger = new Logger(CFUtils::getFunctionName());
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

    $botRepository = new FirestoreBotRepository($isLocal);
    $conversationRepository = new FirestoreConversationRepository($isLocal);
    $chatPromptService = new ChatPromptService();

    $openaiApiKey = getenv("OPENAI_KEY_LINE_AI_BOT") ?: 'dummy';
    $gpt = new yananob\MyTools\Gpt($openaiApiKey, "gpt-4o");
    $commandAndTriggerService = new CommandAndTriggerService($gpt);

    try {
        $bot = $botRepository->findOrDefault($webhookMessage->getTargetId());

        $webSearchTool = null;
        if ($openaiApiKey !== 'dummy') {
            try {
                $openaiClient = OpenAI::client($openaiApiKey);
                $webSearchTool = new MyApp\Infrastructure\Search\OpenAIWebSearchTool($openaiClient, "gpt-4o-mini");
            } catch (\Exception $e) {
                error_log("Failed to initialize WebSearchTool: " . $e->getMessage());
            }
        }

        $chatService = new ChatApplicationService(
            $bot,
            $botRepository,
            $conversationRepository,
            $chatPromptService,
            $gpt,
            $webSearchTool
        );
    } catch (\Exception $e) {
        $logger->log("Failed to initialize ChatApplicationService for target {$webhookMessage->getTargetId()}: " . $e->getMessage());
        return new Response(500, ['Content-Type' => 'application/json'], '{"result": "error", "message": "Bot initialization failed."}');
    }

    $line = __getLineInstance();
    $line->showLoading(
        bot: $chatService->getLineTarget(),
        targetId: $webhookMessage->getTargetId(),
    );

    $answer = "";
    $quickReply = null;
    if ($webhookMessage->getType() === LineWebhookMessage::TYPE_MESSAGE) {
        $command = $commandAndTriggerService->judgeCommand($webhookMessage->getMessage());
        switch ($command) {
            case Command::ShowHelp:
                $answer = Messages::HELP;
                break;

            case Command::AddOneTimeTrigger:
                $trigger = $commandAndTriggerService->generateOneTimeTrigger($webhookMessage->getMessage());
                $chatService->addTimerTrigger($trigger);
                $answer = "タイマーを追加しました：" . $trigger;  // TODO: メッセージに
                break;

            case Command::AddDailyTrigger:
                $trigger = $commandAndTriggerService->generateDailyTrigger($webhookMessage->getMessage());
                $chatService->addTimerTrigger($trigger);
                $answer = "タイマーを追加しました：" . $trigger;  // TODO: メッセージに
                break;

            case Command::RemoveTrigger:
                $answer = "どのタイマーを止めますか？";
                $quickReply = LineTools::convertTriggersToQuickReply(Consts::CMD_REMOVE_TRIGGER, $chatService->getTriggers());
                break;

            default:
                $answer = $chatService->getAnswer(
                    applyRecentConversations: true,
                    message: $webhookMessage->getMessage(),
                );
                $chatService->storeConversations(
                    message: $webhookMessage->getMessage(),
                    answer: $answer,
                );
                break;
        }
    } elseif ($webhookMessage->getType() === LineWebhookMessage::TYPE_POSTBACK) {
        parse_str($webhookMessage->getPostbackData(), $params);
        switch ($params["command"]) {
            case Consts::CMD_REMOVE_TRIGGER:
                $chatService->deleteTrigger($params["id"]);
                $answer = "削除しました：" . $params["trigger"];  // TODO: メッセージに
                break;

            default:
                throw new \Exception("Unsupported command: " . $params["command"]);
        }
    } else {
        throw new \Exception("Unsupported message type: " . $webhookMessage->getType());
    }

    $line->sendReply(
        bot: $chatService->getLineTarget(),
        message: $answer,
        replyToken: $webhookMessage->getReplyToken(),
        quickReply: $quickReply,
    );
        
    return new Response(200, $headers, '{"result": "ok"}');
}

FunctionsFramework::cloudEvent('main_event', 'main_event');
function main_event(CloudEventInterface $event): void
{
    $logger = new Logger(CFUtils::getFunctionName());
    $logger->logSplitter();
    $isLocal = CFUtils::isLocalEvent($event);
    $logger->log("Running as " . ($isLocal ? "local" : "cloud") . " mode");

    $line = __getLineInstance();
    $botRepository = new FirestoreBotRepository($isLocal);
    $conversationRepository = new FirestoreConversationRepository($isLocal); // Needed for ChatApplicationService constructor
    $chatPromptService = new ChatPromptService();

    $openaiApiKey = getenv("OPENAI_KEY_LINE_AI_BOT") ?: 'dummy';
    $gpt = new yananob\MyTools\Gpt($openaiApiKey, "gpt-4o");

    $webSearchTool = null;
    if ($openaiApiKey !== 'dummy') {
        try {
            $openaiClient = OpenAI::client($openaiApiKey);
            $webSearchTool = new MyApp\Infrastructure\Search\OpenAIWebSearchTool($openaiClient, "gpt-4o-mini");
        } catch (\Exception $e) {
            error_log("Failed to initialize WebSearchTool in main_event: " . $e->getMessage());
        }
    }

    foreach ($botRepository->getAllUserBots() as $botUser) {
        foreach ($botUser->getTriggers() as $trigger) {
            // Ensure $trigger is an instance of TimerTrigger or has shouldRunNow
            $logger->log("Processing trigger for user: {$botUser->getId()}. Trigger details: " . (string)$trigger); // Log basic trigger info

            if (!$trigger instanceof TimerTrigger) {
                // Log or handle cases where trigger is not a TimerTrigger, if other types exist
                $logger->log("Skipping trigger for user {$botUser->getId()} as it's not a TimerTrigger. Trigger: " . (string)$trigger);
                continue;
            }
            
            $logger->log("user: {$botUser->getId()}, trigger: {$trigger}");
            if ($trigger->getEvent() !== "timer") { // This check might be redundant if only TimerTriggers are stored/expected
                continue;
            }

            // Add these logs BEFORE the condition:
            $currentTimeForCheck = new Carbon\Carbon(timezone: new \DateTimeZone(Consts::TIMEZONE));
            $logger->log("trigger_function: About to call shouldRunNow for trigger ID " . $trigger->getId() . " for user {$botUser->getId()}");
            $logger->log("trigger_function: Current time is " . $currentTimeForCheck->toString() . " (TZ: " . $currentTimeForCheck->getTimezone()->getName() . ")");
            $logger->log("trigger_function: Trigger details: Date='{$trigger->getDate()}', Time='{$trigger->getTime()}', ActualDate='{$trigger->getActualDate()}', Request='{$trigger->getRequest()}'");

            if (!$trigger->shouldRunNow(TIMER_TRIGGERED_BY_N_MINS)) {
                continue;
            }

            try {
                $chatService = new ChatApplicationService(
                    $botUser,
                    $botRepository, // Pass the already instantiated repository
                    $conversationRepository, // Pass the already instantiated repository
                    $chatPromptService,
                    $gpt,
                    $webSearchTool
                );
            } catch (\Exception $e) {
                $logger->log("TRIGGER: Failed to initialize ChatApplicationService for user {$botUser->getId()}: " . $e->getMessage());
                continue; // Skip to next botUser
            }

            $answer =  $chatService->askRequest(
                applyRecentConversations: true,
                requestMessage: $trigger->getRequest()
            );
            $line->sendPush(
                bot: $chatService->getLineTarget(),
                targetId: $botUser->getId(),
                message: $answer,
            );
        }
    }

    $logger->log("Finished.");
}

function __getLineInstance()
{
    $lineConfig = json_decode(getenv("LINE_TOKENS_N_TARGETS"), true);
    return new Line($lineConfig["tokens"], $lineConfig["target_ids"]);
}
