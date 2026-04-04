<?php declare(strict_types=1);

namespace App\Domain\Bot;

interface BotRepository
{
    /**
     * Finds a Bot by its ID.
     *
     * @param string $id The ID of the Bot to find.
     * @return Bot The Bot object.
     * @throws \App\Domain\Exception\BotNotFoundException If the Bot is not found.
     */
    public function findById(string $id): Bot;

    /**
     * Finds a Bot by its ID, or returns a new Bot instance with the default settings if not found.
     *
     * @param string $id The ID of the Bot to find.
     * @return Bot The Bot object.
     */
    public function findOrDefault(string $id): Bot;

    /**
     * Finds the default Bot configuration.
     *
     * @return Bot The default Bot object.
     */
    public function findDefault(): Bot;

    /**
     * Saves a Bot aggregate.
     * This includes its characteristics, requests, line target, and triggers.
     *
     * @param Bot $bot The Bot object to save.
     * @return void
     */
    public function save(Bot $bot): void;

    /**
     * Returns an array of all user Bot objects (excluding the default).
     *
     * @return Bot[] An array of Bot objects.
     */
    public function getAllUserBots(): array;
}
