<?php declare(strict_types=1);

namespace App\Application;

use App\Domain\Bot\Bot;
use App\Domain\Bot\Service\CommandAndTriggerService;
use App\Domain\Bot\ValueObject\Command;
use App\Domain\Bot\Trigger\TimerTrigger;
use App\Infrastructure\Gcp\CloudFunctionUtils;
use App\Application\CommandHandler\CommandHandlerInterface;
use App\Application\CommandHandler\PostbackHandlerInterface;
use App\Domain\Bot\ValueObject\Message;
use App\Domain\Bot\Messages;
use App\Domain\Exception\HandlerNotFoundException;

class ChatApplicationService
{
    private CommandAndTriggerService $commandAndTriggerService;
    private Bot $bot;
    /** @var CommandHandlerInterface[] */
    private array $messageHandlers;
    /** @var PostbackHandlerInterface[] */
    private array $postbackHandlers;

    /**
     * @param Bot $bot
     * @param CommandAndTriggerService $commandAndTriggerService
     * @param CommandHandlerInterface[] $messageHandlers
     * @param PostbackHandlerInterface[] $postbackHandlers
     */
    public function __construct(
        Bot $bot,
        CommandAndTriggerService $commandAndTriggerService,
        array $messageHandlers = [],
        array $postbackHandlers = []
    ) {
        $this->bot = $bot;
        $this->commandAndTriggerService = $commandAndTriggerService;
        $this->messageHandlers = $messageHandlers;
        $this->postbackHandlers = $postbackHandlers;
    }

    public function handleMessage(string $messageContent): BotResponse
    {
        $command = $this->commandAndTriggerService->judgeCommand($messageContent);
        $message = new Message($messageContent, isSystem: false);

        foreach ($this->messageHandlers as $handler) {
            if ($handler->canHandle($command)) {
                return $handler->handle($message, $this->bot, $command);
            }
        }

        throw new HandlerNotFoundException("No handler found for command: " . $command->value);
    }

    public function handleTrigger(TimerTrigger $trigger): BotResponse
    {
        // Prepend a hint to GPT to ensure it understands this is a timer execution.
        // This prevents GPT from responding with "Timer set" again.
        $messageContent = Messages::SYSTEM_TIMER_INSTRUCTION . $trigger->getRequest();
        $message = new Message($messageContent, isSystem: true);
        $command = Command::Other;

        foreach ($this->messageHandlers as $handler) {
            if ($handler->canHandle($command)) {
                return $handler->handle($message, $this->bot, $command);
            }
        }

        throw new HandlerNotFoundException("No handler found for command: " . $command->value);
    }

    public function handlePostback(string $data): BotResponse
    {
        parse_str($data, $params);
        $command = $params["command"] ?? "";

        foreach ($this->postbackHandlers as $handler) {
            if ($handler->canHandle($command)) {
                return $handler->handle($params, $this->bot);
            }
        }

        throw new HandlerNotFoundException("Unsupported postback command: " . $command);
    }

    public function getLineTarget(): string
    {
        return CloudFunctionUtils::isTestingEnv() ? "test" : $this->bot->getLineTarget();
    }
}
