<?php

declare(strict_types=1);

namespace App\Application\CommandHandler;

use App\Application\BotResponse;
use App\Domain\Bot\Bot;
use App\Domain\Bot\ValueObject\Command;
use App\Domain\Bot\ValueObject\Message;
use App\Domain\Exception\HandlerNotFoundException;

class CommandHandlerDispatcher
{
    /** @var CommandHandlerInterface[] */
    private array $messageHandlers;
    /** @var PostbackHandlerInterface[] */
    private array $postbackHandlers;

    /**
     * @param CommandHandlerInterface[] $messageHandlers
     * @param PostbackHandlerInterface[] $postbackHandlers
     */
    public function __construct(array $messageHandlers, array $postbackHandlers)
    {
        $this->messageHandlers = $messageHandlers;
        $this->postbackHandlers = $postbackHandlers;
    }

    /**
     * @param Command $command
     * @param Message $message
     * @param Bot $bot
     * @return BotResponse
     * @throws HandlerNotFoundException
     */
    public function dispatchMessage(Command $command, Message $message, Bot $bot): BotResponse
    {
        foreach ($this->messageHandlers as $handler) {
            if ($handler->canHandle($command)) {
                return $handler->handle($message, $bot, $command);
            }
        }

        throw new HandlerNotFoundException("No handler found for command: " . $command->value);
    }

    /**
     * @param string $commandValue
     * @param array<string, mixed> $params
     * @param Bot $bot
     * @return BotResponse
     * @throws HandlerNotFoundException
     */
    public function dispatchPostback(string $commandValue, array $params, Bot $bot): BotResponse
    {
        foreach ($this->postbackHandlers as $handler) {
            if ($handler->canHandle($commandValue)) {
                return $handler->handle($params, $bot);
            }
        }

        throw new HandlerNotFoundException("Unsupported postback command: " . $commandValue);
    }
}
