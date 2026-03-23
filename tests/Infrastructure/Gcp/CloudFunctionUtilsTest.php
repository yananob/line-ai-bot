<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Gcp;

use App\Infrastructure\Gcp\CloudFunctionUtils;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use CloudEvents\V1\CloudEventInterface;

final class CloudFunctionUtilsTest extends TestCase
{
    private array $originalEnv;

    protected function setUp(): void
    {
        $this->originalEnv = $_ENV;
    }

    protected function tearDown(): void
    {
        $_ENV = $this->originalEnv;
        putenv('K_SERVICE=');
    }

    public function test_isLocalHttp_returns_true_for_localhost(): void
    {
        $requestMock = $this->createMock(ServerRequestInterface::class);
        $requestMock->method('getHeader')->with('Host')->willReturn(['localhost:8080']);

        $this->assertTrue(CloudFunctionUtils::isLocalHttp($requestMock));
    }

    public function test_isLocalHttp_returns_false_for_external_host(): void
    {
        $requestMock = $this->createMock(ServerRequestInterface::class);
        $requestMock->method('getHeader')->with('Host')->willReturn(['example.com']);

        $this->assertFalse(CloudFunctionUtils::isLocalHttp($requestMock));
    }

    public function test_isLocalEvent_returns_true_for_specific_id(): void
    {
        $eventMock = $this->createMock(CloudEventInterface::class);
        $eventMock->method('getId')->willReturn('9999999999');

        $this->assertTrue(CloudFunctionUtils::isLocalEvent($eventMock));
    }

    public function test_isLocalEvent_returns_false_for_other_id(): void
    {
        $eventMock = $this->createMock(CloudEventInterface::class);
        $eventMock->method('getId')->willReturn('1234567890');

        $this->assertFalse(CloudFunctionUtils::isLocalEvent($eventMock));
    }

    public function test_getFunctionName_returns_env_var(): void
    {
        putenv('K_SERVICE=my-function');
        $this->assertSame('my-function', CloudFunctionUtils::getFunctionName());
    }

    public function test_getFunctionName_returns_default_when_empty(): void
    {
        putenv('K_SERVICE=');
        $this->assertSame('default', CloudFunctionUtils::getFunctionName('default'));
    }

    public function test_isTestingEnv_returns_true_when_k_service_empty(): void
    {
        putenv('K_SERVICE=');
        $this->assertTrue(CloudFunctionUtils::isTestingEnv());
    }

    public function test_isTestingEnv_returns_true_when_k_service_contains_test(): void
    {
        putenv('K_SERVICE=my-test-function');
        $this->assertTrue(CloudFunctionUtils::isTestingEnv());
    }

    public function test_isTestingEnv_returns_false_when_k_service_is_prod(): void
    {
        putenv('K_SERVICE=my-prod-function');
        $this->assertFalse(CloudFunctionUtils::isTestingEnv());
    }
}
