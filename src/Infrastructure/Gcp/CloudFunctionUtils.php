<?php

declare(strict_types=1);

namespace App\Infrastructure\Gcp;

use App\AppConfig;
use Psr\Http\Message\ServerRequestInterface;
use CloudEvents\V1\CloudEventInterface;

/**
 * Cloud Functions実行環境に関するユーティリティクラス。
 */
class CloudFunctionUtils
{
    /**
     * ローカル環境からのHTTPリクエストであるかを判定します。
     */
    public static function isLocalHttp(ServerRequestInterface $request): bool
    {
        $host = $request->getHeader("Host")[0] ?? '';
        return str_contains($host, "localhost") || str_contains($host, "127.0.0.1");
    }

    /**
     * ローカルイベント（テスト用イベント）であるかを判定します。
     */
    public static function isLocalEvent(CloudEventInterface $event): bool
    {
        return ($event->getId() === "9999999999");
    }

    /**
     * Cloud Functionsの関数名を取得します。
     */
    public static function getFunctionName(string $defaultName = ''): string
    {
        return AppConfig::getFunctionName($defaultName);
    }

    /**
     * テスト環境のサービスであるかを判定します。
     */
    public static function isTestingEnv(): bool
    {
        $funcName = self::getFunctionName('');
        if (empty($funcName)) {
            return true;
        }
        return str_contains($funcName, "test");
    }
}
