<?php declare(strict_types=1);

namespace App\Domain\Bot\Service;

use App\Domain\Bot\Bot;
use App\Domain\Bot\Trigger\Trigger;
use App\Domain\Bot\ValueObject\BotPersonalityConfig;
use App\Domain\Bot\ValueObject\StringList;

class BotFactory
{
    /**
     * @param string $id
     * @param array<string, mixed> $data
     * @param array<string, Trigger> $triggers
     * @param Bot|null $defaultBot
     * @return Bot
     */
    public static function create(
        string $id,
        array $data,
        array $triggers = [],
        ?Bot $defaultBot = null
    ): Bot {
        $personality = new BotPersonalityConfig(
            new StringList($data['bot_characteristics'] ?? []),
            new StringList($data['human_characteristics'] ?? []),
            new StringList($data['requests'] ?? [])
        );

        return new Bot(
            $id,
            $data['bot_name'] ?? '',
            $personality,
            $data['line_target'] ?? '',
            $triggers,
            $defaultBot
        );
    }
}
