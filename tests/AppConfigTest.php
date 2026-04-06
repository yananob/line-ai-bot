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

    public function test_getEnvironment_returns_value_from_env(): void
    {
        putenv('APP_ENV=production');
        $this->assertSame('production', AppConfig::getEnvironment());

        putenv('APP_ENV=test');
        $this->assertSame('test', AppConfig::getEnvironment());
    }

    public function test_getEnvironment_throws_exception_if_not_set(): void
    {
        putenv('APP_ENV'); // Unset
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('APP_ENV environment variable is not set.');
        AppConfig::getEnvironment();
    }

    public function test_getFirestoreRootCollection_returns_correct_value(): void
    {
        putenv('APP_ENV=production');
        $this->assertSame('ai-bot', AppConfig::getFirestoreRootCollection());

        putenv('APP_ENV=test');
        $this->assertSame('ai-bot-test', AppConfig::getFirestoreRootCollection());

        putenv('APP_ENV=development');
        $this->assertSame('ai-bot-test', AppConfig::getFirestoreRootCollection());
    }

    public function test_getBasePath_returns_correct_value(): void
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

    public function test_isDevelopment_returns_true_only_in_development(): void
    {
        putenv('APP_ENV=development');
        $this->assertTrue(AppConfig::isDevelopment());

        putenv('APP_ENV=production');
        $this->assertFalse(AppConfig::isDevelopment());
    }

    public function test_isTest_returns_true_only_in_test(): void
    {
        putenv('APP_ENV=test');
        $this->assertTrue(AppConfig::isTest());

        putenv('APP_ENV=production');
        $this->assertFalse(AppConfig::isTest());
    }

    public function test_getFunctionName_returns_k_service(): void
    {
        putenv('K_SERVICE=hello-func');
        $this->assertSame('hello-func', AppConfig::getFunctionName());

        putenv('K_SERVICE');
        $this->assertSame('default', AppConfig::getFunctionName('default'));
    }
}
