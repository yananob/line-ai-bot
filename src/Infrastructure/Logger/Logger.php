<?php

declare(strict_types=1);

namespace App\Infrastructure\Logger;

class Logger
{
    /** @var resource */
    private $fp;
    private string $title;

    public function __construct(string $title = '')
    {
        $this->title = $title;
        $this->fp = fopen(getenv('LOGGER_OUTPUT') ?: 'php://stderr', 'wb');
    }

    public function log(mixed $message): void
    {
        if (is_null($message)) {
            $message = "";
        } else if (is_array($message) || is_object($message)) {
            $message = json_encode($message);
        }

        $log_message = (string)$message;
        if (!empty($this->title)) {
            $log_message = "[{$this->title}] {$log_message}";
        }

        fwrite($this->fp, $log_message . PHP_EOL);
    }

    public function logSplitter(string $char = "-", int $count = 120): void
    {
        $this->log(str_repeat($char, $count));
    }

    public function __destruct()
    {
        if (is_resource($this->fp)) {
            fclose($this->fp);
        }
    }
}
