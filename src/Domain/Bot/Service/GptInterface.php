<?php

declare(strict_types=1);

namespace App\Domain\Bot\Service;

interface GptInterface
{
    /**
     * @param string $context
     * @param string $message
     * @param array $options
     * @return string
     */
    public function getAnswer(string $context, string $message, array $options = []): string;
}
