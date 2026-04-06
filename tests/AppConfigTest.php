<?php

declare(strict_types=1);

namespace Tests;

use App\AppConfig;
use PHPUnit\Framework\TestCase;

final class AppConfigTest extends TestCase
{
    private string|false $originalEnv;
    private string|false $originalKService;

    protected function setUp(): void
    {
        $this->originalEnv = getenv('APP_ENV');
        $this->originalKService = getenv('K_SERVICE');
    }

    protected function tearDown(): void
    {
        if ($this->originalEnv !== false) {
            putenv("APP_ENV={$this->originalEnv}");
        } else {
            putenv("APP_ENV");
        }

        if ($this->originalKService !== false) {
            putenv("K_SERVICE={$this->originalKService}");
        } else {
            putenv("K_SERVICE");
        }
    }

    public function test_getEnvironmentは環境変数から値を取得する(): void
    {
        putenv('APP_ENV=production');
        $this->assertSame('production', AppConfig::getEnvironment());

        putenv('APP_ENV=test');
        $this->assertSame('test', AppConfig::getEnvironment());
    }

    public function test_getEnvironmentは環境変数が設定されていない場合に例外を投げる(): void
    {
        putenv('APP_ENV'); // Unset
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('APP_ENV environment variable is not set.');
        AppConfig::getEnvironment();
    }

    public function test_getFirestoreRootCollectionは正しい値を返す(): void
    {
        putenv('APP_ENV=production');
        $this->assertSame('ai-bot', AppConfig::getFirestoreRootCollection());

        putenv('APP_ENV=test');
        $this->assertSame('ai-bot-test', AppConfig::getFirestoreRootCollection());

        putenv('APP_ENV=development');
        $this->assertSame('ai-bot-test', AppConfig::getFirestoreRootCollection());
    }

    public function test_getBasePathは正しい値を返す(): void
    {
        putenv('APP_ENV=production');
        $this->assertSame('/line-ai-bot', AppConfig::getBasePath());

        putenv('APP_ENV=test');
        putenv('K_SERVICE=my-test-func');
        $this->assertSame('/my-test-func', AppConfig::getBasePath());

        putenv('K_SERVICE'); // Unset
        $this->assertSame('/line-ai-bot-test', AppConfig::getBasePath());

        putenv('APP_ENV=development');
        $this->assertSame('', AppConfig::getBasePath());
    }

    public function test_isDevelopmentはdevelopmentの場合のみtrueを返す(): void
    {
        putenv('APP_ENV=development');
        $this->assertTrue(AppConfig::isDevelopment());

        putenv('APP_ENV=production');
        $this->assertFalse(AppConfig::isDevelopment());
    }

    public function test_isTestはtestの場合のみtrueを返す(): void
    {
        putenv('APP_ENV=test');
        $this->assertTrue(AppConfig::isTest());

        putenv('APP_ENV=production');
        $this->assertFalse(AppConfig::isTest());
    }

    public function test_getFunctionNameはK_SERVICEを返す(): void
    {
        putenv('K_SERVICE=hello-func');
        $this->assertSame('hello-func', AppConfig::getFunctionName());

        putenv('K_SERVICE');
        $this->assertSame('default', AppConfig::getFunctionName('default'));
    }
}
