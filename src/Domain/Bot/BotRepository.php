<?php declare(strict_types=1);

namespace MyApp\Domain\Bot;

interface BotRepository
{
    /**
     * Finds a Bot by its ID.
     *
     * @param string $id The ID of the Bot to find.
     * @return Bot|null The Bot object if found, otherwise null.
     */
    public function findById(string $id): ?Bot;

    /**
     * Finds the default Bot configuration.
     *
     * @return Bot|null The default Bot object if found, otherwise null.
     */
    public function findDefault(): ?Bot;

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
