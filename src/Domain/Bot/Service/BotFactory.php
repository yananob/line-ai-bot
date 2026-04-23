<?php declare(strict_types=1);

namespace App\Domain\Bot\Service;

use App\Domain\Bot\Bot;
use App\Domain\Bot\Trigger\Trigger;

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
        $bot = new Bot($id, $defaultBot);
        $bot->setName($data['bot_name'] ?? '');
        $bot->setBotCharacteristics($data['bot_characteristics'] ?? []);
        $bot->setHumanCharacteristics($data['human_characteristics'] ?? []);
        $bot->setConfigRequests($data['requests'] ?? []);
        $bot->setLineTarget($data['line_target'] ?? '');
        $bot->setTriggers($triggers);

        return $bot;
    }
}
