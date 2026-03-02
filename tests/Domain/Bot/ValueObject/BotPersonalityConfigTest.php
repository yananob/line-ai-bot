<?php declare(strict_types=1);

namespace MyApp\Tests\Domain\Bot\ValueObject;

use PHPUnit\Framework\TestCase;
use MyApp\Domain\Bot\ValueObject\BotPersonalityConfig;
use MyApp\Domain\Bot\ValueObject\StringList;

class BotPersonalityConfigTest extends TestCase
{
    public function test_getters_return_correct_instances(): void
    {
        $botChars = new StringList(['bot']);
        $humanChars = new StringList(['human']);
        $config = new BotPersonalityConfig($botChars, $humanChars);

        $this->assertSame($botChars, $config->getBotCharacteristics());
        $this->assertSame($humanChars, $config->getHumanCharacteristics());
    }

    public function test_isEmpty_returns_true_if_both_lists_are_empty(): void
    {
        $config = new BotPersonalityConfig(new StringList([]), new StringList([]));
        $this->assertTrue($config->isEmpty());
    }

    public function test_isEmpty_returns_false_if_bot_chars_not_empty(): void
    {
        $config = new BotPersonalityConfig(new StringList(['bot']), new StringList([]));
        $this->assertFalse($config->isEmpty());
    }

    public function test_isEmpty_returns_false_if_human_chars_not_empty(): void
    {
        $config = new BotPersonalityConfig(new StringList([]), new StringList(['human']));
        $this->assertFalse($config->isEmpty());
    }
}
