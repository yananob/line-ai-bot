<?php declare(strict_types=1);

namespace MyApp\Domain\Conversation;

interface ConversationRepository
{
    /**
     * Retrieves a list of Conversation objects for a given bot ID,
     * ordered by creation time (most recent first).
     *
     * @param string $botId The ID of the bot.
     * @param int $limit The maximum number of conversation entries to retrieve.
     * @return Conversation[] An array of Conversation objects.
     */
    public function findByBotId(string $botId, int $limit = 20): array;

    /**
     * Saves a Conversation entry.
     * If the conversation entry is new (no ID), the repository should handle
     * assigning one upon persistence if a specific ID scheme (e.g., sequential) is used,
     * or use Firestore's auto-generated ID.
     *
     * @param Conversation $conversation The Conversation object to save.
     * @return void
     */
    public function save(Conversation $conversation): void;

    /**
     * Deletes the most recent $count conversation entries for a given bot ID.
     *
     * @param string $botId The ID of the bot.
     * @param int $count The number of most recent entries to delete.
     * @return void
     */
    public function deleteByBotId(string $botId, int $count): void;
}
