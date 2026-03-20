<?php declare(strict_types=1);

namespace App\Application;

use Exception;
use App\Domain\Bot\Bot;
use App\Domain\Bot\Service\CommandAndTriggerService;
use App\Infrastructure\Gcp\CloudFunctionUtils;
use App\Application\CommandHandler\CommandHandlerInterface;
use App\Application\CommandHandler\PostbackHandlerInterface;

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

    public function handleMessage(string $message): BotResponse
    {
        $command = $this->commandAndTriggerService->judgeCommand($message);

        foreach ($this->messageHandlers as $handler) {
            if ($handler->canHandle($command)) {
                return $handler->handle($message, $this->bot, $command);
            }
        }

        throw new Exception("No handler found for command: " . $command->value);
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

        throw new Exception("Unsupported postback command: " . $command);
    }

    public function getLineTarget(): string
    {
        return CloudFunctionUtils::isTestingEnv() ? "test" : $this->bot->getLineTarget();
    }
}
