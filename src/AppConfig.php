<?php

declare(strict_types=1);

namespace App;

/**
 * アプリケーション環境に基づいて設定値を提供します。
 * 環境は`APP_ENV`環境変数によって決定されます。
 *
 * サポートされる環境: 'production', 'test', 'development'。
 * `APP_ENV`が設定されていない場合、デフォルトは'development'です。
 */
class AppConfig
{
    /**
     * 現在のアプリケーション環境を取得します。
     *
     * @return string 現在の環境 ('production', 'test', または 'development')。
     */
    public static function getEnvironment(): string
    {
        return getenv('APP_ENV') ?: 'development';
    }

    /**
     * Firestoreのルートコレクション名を取得します。
     *
     * @return string Firestoreコレクションの名前。
     */
    public static function getFirestoreRootCollection(): string
    {
        return match (self::getEnvironment()) {
            'production' => 'ai-bot',
            'test' => 'ai-bot-test',
            default => 'ai-bot-test',
        };
    }

    /**
     * アプリケーションのベースパスを取得します。
     *
     * @return string ベースパス。
     */
    public static function getBasePath(): string
    {
        $env = self::getEnvironment();

        if ($env === 'production') {
            return '/line-ai-bot';
        }

        if ($env === 'test') {
            $functionUrl = getenv('K_SERVICE');
            if ($functionUrl) {
                return '/' . $functionUrl;
            }

            return '/line-ai-bot-test';
        }

        // development (local)
        return '';
    }

    /**
     * LINEメッセージ配信のターゲットとなるユーザー/グループIDを取得します。
     *
     * @return string LINEターゲットID。
     */
    public static function getLineDeliverTarget(): string
    {
        return match (self::getEnvironment()) {
            'production' => 'ai-bot',
            'test' => 'nobu',
            default => 'nobu',
        };
    }

    /**
     * 静的ファイルのベースURLを取得します。
     *
     * @return string ベースURL。
     */
    public static function getStaticBaseUrl(): string
    {
        return match (self::getEnvironment()) {
            'production' => 'https://storage.googleapis.com/line-ai-bot-static',
            'test' => 'https://storage.googleapis.com/line-ai-bot-static/test',
            default => '/public',
        };
    }

    /**
     * 開発環境であるかどうかを返します。
     *
     * @return bool 開発環境である場合はtrue。
     */
    public static function isDevelopment(): bool
    {
        return self::getEnvironment() === 'development';
    }

    /**
     * テスト環境であるかどうかを返します。
     *
     * @return bool テスト環境である場合はtrue。
     */
    public static function isTest(): bool
    {
        return self::getEnvironment() === 'test';
    }

    /**
     * Cloud Functionsの関数名を取得します。
     *
     * @param string $defaultName 環境変数 'K_SERVICE' が未設定の場合に返すデフォルトの関数名。
     * @return string 関数名。
     */
    public static function getFunctionName(string $defaultName = ''): string
    {
        $funcName = getenv('K_SERVICE');
        return ($funcName === false) ? $defaultName : $funcName;
    }
}
