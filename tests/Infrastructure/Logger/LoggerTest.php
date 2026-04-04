<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Logger;

use App\Infrastructure\Logger\Logger;
use PHPUnit\Framework\TestCase;

final class LoggerTest extends TestCase
{
    private string $tempFile;

    protected function setUp(): void
    {
        $this->tempFile = tempnam(sys_get_temp_dir(), 'log');
        putenv("LOGGER_OUTPUT=" . $this->tempFile);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }
        putenv("LOGGER_OUTPUT"); // Reset
    }

    public function test_log_writes_string_to_file(): void
    {
        $logger = new Logger('test-app');
        $logger->log('Hello, world!');

        $content = file_get_contents($this->tempFile);
        $this->assertStringContainsString('test-app.INFO: Hello, world!', $content);
    }

    public function test_log_encodes_array(): void
    {
        $logger = new Logger('test-app');
        $data = ['key' => 'value'];
        $logger->log($data);

        $content = file_get_contents($this->tempFile);
        $this->assertStringContainsString('test-app.INFO: {"key":"value"}', $content);
    }

    public function test_log_encodes_object(): void
    {
        $logger = new Logger('test-app');
        $obj = new \stdClass();
        $obj->foo = 'bar';
        $logger->log($obj);

        $content = file_get_contents($this->tempFile);
        $this->assertStringContainsString('test-app.INFO: {"foo":"bar"}', $content);
    }

    public function test_log_handles_null(): void
    {
        $logger = new Logger('test-app');
        $logger->log(null);

        $content = file_get_contents($this->tempFile);
        // Monolog logs empty string as empty.
        $this->assertStringContainsString('test-app.INFO:', $content);
    }

    public function test_logSplitter(): void
    {
        $logger = new Logger('test-app');
        $logger->logSplitter('=', 10);

        $content = file_get_contents($this->tempFile);
        $this->assertStringContainsString('test-app.INFO: ========== ', $content);
    }
}
