<?php

declare(strict_types=1);

namespace Tests\Domain\Bot\ValueObject;

use PHPUnit\Framework\TestCase;
use App\Domain\Bot\ValueObject\Command;

final class CommandTest extends TestCase
{
    public function test_文字列から正しいEnumケースを取得できる(): void
    {
        $this->assertSame(Command::AddOneTimeTrigger, Command::from("3"));
        $this->assertSame(Command::AddDailyTrigger, Command::from("4"));
        $this->assertSame(Command::RemoveTrigger, Command::from("5"));
        $this->assertSame(Command::ShowHelp, Command::from("8"));
        $this->assertSame(Command::Other, Command::from("9"));
    }

    public function test_不正な文字列の場合は例外を投げる(): void
    {
        $this->expectException(\ValueError::class);
        Command::from("unknown");
    }

    public function test_tryFromで安全に変換できる(): void
    {
        $this->assertSame(Command::AddOneTimeTrigger, Command::tryFrom("3"));
        $this->assertNull(Command::tryFrom("invalid"));
    }
}
