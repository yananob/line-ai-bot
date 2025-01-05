<?php

declare(strict_types=1);

use MyApp\BotConfigsStore;

final class BotConfigsStoreTest extends PHPUnit\Framework\TestCase
{
    private BotConfigsStore $botConfigsStore;

    protected function setUp(): void
    {
        $this->botConfigsStore = new BotConfigsStore(isTest: true);
    }

    public function testGetUsers(): void
    {
        $users = $this->botConfigsStore->getUsers();
        $this->assertSame(
            ["TARGET_ID_AUTOTEST", "TARGET_ID_AUTOTEST2"],
            array_map(function ($user) {
                return $user->getId();
            }, $users)
        );
    }

    public function testGet_exists(): void
    {
        $botConfig = $this->botConfigsStore->getConfig("TARGET_ID_AUTOTEST");
        $this->assertNotEmpty($botConfig);
        // $this->assertTrue($botConfig->isChatMode());
        $this->assertNotEmpty($botConfig->getBotCharacteristics());
        $this->assertNotEmpty($botConfig->getHumanCharacteristics());
        $this->assertNotEmpty($botConfig->getConfigRequests(usePersonal: true, useDefault: true));
    }

    public function testGet_notExists(): void
    {
        $botConfig = $this->botConfigsStore->getConfig("TARGET_ID_NOT_EXISTS");
        $this->assertNotEmpty($botConfig);
    }

    public function testGetDefault(): void
    {
        $botConfig = $this->botConfigsStore->getDefaultConfig();
        // $this->assertTrue($botConfig->isChatMode());
        $this->assertNotEmpty($botConfig->getBotCharacteristics());
        $this->assertEmpty($botConfig->getHumanCharacteristics());
        $this->assertNotEmpty($botConfig->getConfigRequests(usePersonal: true, useDefault: false));
    }

    // public function testExists_true(): void
    // {
    //     $this->assertTrue($this->botConfigsStore->exists("TARGET_ID_AUTOTEST"));
    // }
    // public function testExists_false(): void
    // {
    //     $this->assertFalse($this->botConfigsStore->exists("TARGET_ID_NOT_EXISTS"));
    // }
}
