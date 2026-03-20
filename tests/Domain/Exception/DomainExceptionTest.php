<?php declare(strict_types=1);

namespace Tests\Domain\Exception;

use PHPUnit\Framework\TestCase;
use App\Domain\Exception\BotNotFoundException;
use App\Domain\Exception\InvalidWebhookEventException;
use App\Domain\Exception\DomainException;

final class DomainExceptionTest extends TestCase
{
    public function test_BotNotFoundExceptionはDomainExceptionを継承している(): void
    {
        $exception = new BotNotFoundException("Bot not found");
        $this->assertInstanceOf(DomainException::class, $exception);
        $this->assertEquals("Bot not found", $exception->getMessage());
    }

    public function test_InvalidWebhookEventExceptionはDomainExceptionを継承している(): void
    {
        $exception = new InvalidWebhookEventException("Invalid webhook event");
        $this->assertInstanceOf(DomainException::class, $exception);
        $this->assertEquals("Invalid webhook event", $exception->getMessage());
    }
}
