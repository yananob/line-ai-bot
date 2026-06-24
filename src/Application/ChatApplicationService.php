<?php declare(strict_types=1);

namespace App\Application;

use App\Domain\Bot\Bot;
use App\Domain\Bot\Service\CommandAndTriggerService;
use App\Domain\Bot\ValueObject\Command;
use App\Domain\Bot\Trigger\TimerTrigger;
use App\Infrastructure\Gcp\CloudFunctionUtils;
use App\Application\CommandHandler\CommandHandlerDispatcher;
use App\Domain\Bot\ValueObject\Message;
use App\Domain\Bot\Messages;
use App\Domain\Exception\HandlerNotFoundException;
use App\Infrastructure\Logger\Logger;

class ChatApplicationService
{
    private CommandAndTriggerService $commandAndTriggerService;
    private Bot $bot;
    private CommandHandlerDispatcher $dispatcher;
    private ?Logger $logger;

    public function __construct(
        Bot $bot,
        CommandAndTriggerService $commandAndTriggerService,
        CommandHandlerDispatcher $dispatcher,
        ?Logger $logger = null
    ) {
        $this->bot = $bot;
        $this->commandAndTriggerService = $commandAndTriggerService;
        $this->dispatcher = $dispatcher;
        $this->logger = $logger;
    }

    public function handleMessage(string $messageContent): BotResponse
    {
        $command = $this->commandAndTriggerService->judgeCommand($messageContent);
        if ($this->logger) {
            $this->logger->log("Judged Command: " . $command->value);
        }
        $message = new Message($messageContent, isSystem: false);

        return $this->dispatcher->dispatchMessage($command, $message, $this->bot);
    }

    public function handleTrigger(TimerTrigger $trigger): BotResponse
    {
        // Prepend a hint to GPT to ensure it understands this is a timer execution.
        // This prevents GPT from responding with "Timer set" again.
        $messageContent = Messages::SYSTEM_TIMER_INSTRUCTION . $trigger->getRequest();
        $message = new Message($messageContent, isSystem: true);
        $command = Command::Other;

        return $this->dispatcher->dispatchMessage($command, $message, $this->bot);
    }

    public function handlePostback(string $data): BotResponse
    {
        parse_str($data, $params);
        $command = $params["command"] ?? "";

        return $this->dispatcher->dispatchPostback($command, $params, $this->bot);
    }

    public function getLineTarget(): string
    {
        return CloudFunctionUtils::isTestingEnv() ? "test" : $this->bot->getLineTarget();
    }
}
