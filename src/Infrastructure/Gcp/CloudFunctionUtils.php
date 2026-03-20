<?php

declare(strict_types=1);

namespace App\Infrastructure\Gcp;

use Psr\Http\Message\ServerRequestInterface;
use CloudEvents\V1\CloudEventInterface;

class CloudFunctionUtils
{
    public static function isLocalHttp(ServerRequestInterface $request): bool
    {
        $host = $request->getHeader("Host")[0] ?? '';
        return str_contains($host, "localhost") || str_contains($host, "127.0.0.1");
    }

    public static function isLocalEvent(CloudEventInterface $event): bool
    {
        return ($event->getId() === "9999999999");
    }

    public static function getFunctionName(string $defaultName = ''): string
    {
        $funcName = getenv('K_SERVICE');
        return ($funcName === false || $funcName === '') ? $defaultName : $funcName;
    }

    public static function isTestingEnv(): bool
    {
        $funcName = self::getFunctionName('');
        if (empty($funcName)) {
            return true;
        }
        return str_contains($funcName, "test");
    }
}
