<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Google\CloudFunctions\FunctionsFramework;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use CloudEvents\V1\CloudEventInterface;
use GuzzleHttp\Psr7\Response;
use App\Infrastructure\Logger\Logger;
use App\Infrastructure\Line\LineClient;
use App\Infrastructure\Gcp\CloudFunctionUtils;
use App\Infrastructure\Line\LineWebhookMessage;
use App\Application\ChatApplicationService;
use App\Domain\Bot\Service\ChatPromptService;
use App\Domain\Bot\Service\CommandAndTriggerService;
use App\Infrastructure\Persistence\Firestore\FirestoreBotRepository;
use App\Infrastructure\Persistence\Firestore\FirestoreConversationRepository;
use App\Domain\Bot\Trigger\TimerTrigger;
use App\Application\CommandHandler\CommandHandlerFactory;

const TIMER_TRIGGERED_BY_N_MINS = 10;

FunctionsFramework::http('main_http', 'main_http');
function main_http(ServerRequestInterface $request): ResponseInterface
{
    $logger = new Logger(CloudFunctionUtils::getFunctionName());
    $logger->logSplitter();
    $logger->log("headers: " . json_encode($request->getHeaders()));
    $body = $request->getBody()->getContents();
    $logger->log("body: " . $body);

    $isLocal = CloudFunctionUtils::isLocalHttp($request);
    $logger->log("Running as " . ($isLocal ? "local" : "cloud") . " mode");

    $headers = ['Content-Type' => 'application/json'];

    $webhookMessage = new LineWebhookMessage($body);

    $botRepository = new FirestoreBotRepository($isLocal);
    $conversationRepository = new FirestoreConversationRepository($isLocal);
    $chatPromptService = new ChatPromptService();

    $openaiApiKey = getenv("OPENAI_KEY_LINE_AI_BOT") ?: 'dummy';
    $openaiClient = OpenAI::client($openaiApiKey);
    $gpt = new App\Infrastructure\Gpt\OpenAiGptClient($openaiClient, "gpt-4o");
    $commandAndTriggerService = new CommandAndTriggerService($gpt);

    try {
        $bot = $botRepository->findOrDefault($webhookMessage->getTargetId());

        $webSearchTool = null;
        if ($openaiApiKey !== 'dummy') {
            try {
                $webSearchTool = new App\Infrastructure\Search\OpenAIWebSearchTool($openaiClient, "gpt-5-mini");
            } catch (\Exception $e) {
                $logger->log("Failed to initialize WebSearchTool: " . $e->getMessage());
            }
        }

        $messageHandlers = CommandHandlerFactory::createMessageHandlers(
            $commandAndTriggerService,
            $botRepository,
            $gpt,
            $conversationRepository,
            $chatPromptService,
            $webSearchTool
        );
        $postbackHandlers = CommandHandlerFactory::createPostbackHandlers($botRepository);

        $chatService = new ChatApplicationService(
            $bot,
            $commandAndTriggerService,
            $messageHandlers,
            $postbackHandlers
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

    if ($webhookMessage->getType() === LineWebhookMessage::TYPE_MESSAGE) {
        $botResponse = $chatService->handleMessage($webhookMessage->getMessage());
    } elseif ($webhookMessage->getType() === LineWebhookMessage::TYPE_POSTBACK) {
        $botResponse = $chatService->handlePostback($webhookMessage->getPostbackData());
    } else {
        throw new \Exception("Unsupported message type: " . $webhookMessage->getType());
    }

    $line->sendReply(
        bot: $chatService->getLineTarget(),
        message: $botResponse->getText(),
        replyToken: $webhookMessage->getReplyToken(),
        quickReplyItems: $botResponse->getQuickReply(),
    );
        
    return new Response(200, $headers, '{"result": "ok"}');
}

FunctionsFramework::cloudEvent('main_event', 'main_event');
function main_event(CloudEventInterface $event): void
{
    $logger = new Logger(CloudFunctionUtils::getFunctionName());
    $logger->logSplitter();
    $isLocal = CloudFunctionUtils::isLocalEvent($event);
    $logger->log("Running as " . ($isLocal ? "local" : "cloud") . " mode");

    $line = __getLineInstance();
    $botRepository = new FirestoreBotRepository($isLocal);
    $conversationRepository = new FirestoreConversationRepository($isLocal);
    $chatPromptService = new ChatPromptService();

    $openaiApiKey = getenv("OPENAI_KEY_LINE_AI_BOT") ?: 'dummy';
    $openaiClient = OpenAI::client($openaiApiKey);
    $gpt = new App\Infrastructure\Gpt\OpenAiGptClient($openaiClient, "gpt-4o");
    $commandAndTriggerService = new CommandAndTriggerService($gpt);

    $webSearchTool = null;
    if ($openaiApiKey !== 'dummy') {
        try {
            $webSearchTool = new App\Infrastructure\Search\OpenAIWebSearchTool($openaiClient, "gpt-5-mini");
        } catch (\Exception $e) {
            $logger->log("Failed to initialize WebSearchTool in main_event: " . $e->getMessage());
        }
    }

    $messageHandlers = CommandHandlerFactory::createMessageHandlers(
        $commandAndTriggerService,
        $botRepository,
        $gpt,
        $conversationRepository,
        $chatPromptService,
        $webSearchTool
    );
    $postbackHandlers = CommandHandlerFactory::createPostbackHandlers($botRepository);

    foreach ($botRepository->getAllUserBots() as $botUser) {
        foreach ($botUser->getTriggers() as $trigger) {
            if (!$trigger instanceof TimerTrigger) {
                $logger->log("Skipping trigger for user {$botUser->getId()} as it's not a TimerTrigger.");
                continue;
            }
            
            if ($trigger->getEvent() !== "timer") {
                continue;
            }

            if (!$trigger->shouldRunNow(TIMER_TRIGGERED_BY_N_MINS)) {
                continue;
            }

            try {
                $chatService = new ChatApplicationService(
                    $botUser,
                    $commandAndTriggerService,
                    $messageHandlers,
                    $postbackHandlers
                );
            } catch (\Exception $e) {
                $logger->log("TRIGGER: Failed to initialize ChatApplicationService for user {$botUser->getId()}: " . $e->getMessage());
                continue;
            }

            $answer = $chatService->handleTrigger($trigger)->getText();
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
    return new LineClient($lineConfig["tokens"], $lineConfig["target_ids"]);
}
