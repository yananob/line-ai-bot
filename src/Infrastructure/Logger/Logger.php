<?php

declare(strict_types=1);

namespace App\Infrastructure\Logger;

use Monolog\Logger as MonologLogger;
use Monolog\Handler\StreamHandler;
use Monolog\Level;

class Logger
{
    private MonologLogger $logger;

    public function __construct(string $title = '')
    {
        $this->logger = new MonologLogger($title ?: 'app');
        $this->logger->pushHandler(new StreamHandler(getenv('LOGGER_OUTPUT') ?: 'php://stderr', Level::Debug));
    }

    public function log(mixed $message): void
    {
        if (is_null($message)) {
            $message = "";
        } else if (is_array($message) || is_object($message)) {
            $message = json_encode($message);
        }

        $this->logger->info((string)$message);
    }

    public function logSplitter(string $char = "-", int $count = 120): void
    {
        $this->log(str_repeat($char, $count));
    }
}
