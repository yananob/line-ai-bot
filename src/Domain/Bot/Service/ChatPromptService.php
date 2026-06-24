<?php declare(strict_types=1);

namespace App\Domain\Bot\Service;

use App\Domain\Bot\Bot;
use App\Domain\Conversation\Conversation;
use App\Domain\Bot\ValueObject\StringList;
use App\Domain\Bot\Messages;

class ChatPromptService
{
    /**
     * @param Bot $bot
     * @param Conversation[] $conversations Array of Conversation entities
     * @param StringList $requests Bot's configured requests
     * @param string|null $webSearchResults
     * @return string
     */
    public function generateContext(Bot $bot, array $conversations, StringList $requests, ?string $webSearchResults = null): string
    {
        $replacements = [
            "<bot/characteristics>" => $bot->getBotCharacteristics()->format(),
            "<requests>" => $requests->format(),
        ];

        if (empty($bot->hasHumanCharacteristics())) {
            $replacements["<title/human_characteristics>"] = "";
            $replacements["<human/characteristics>"] = "";
        } else {
            $replacements["<title/human_characteristics>"] = "【話し相手の情報】";
            $replacements["<human/characteristics>"] = $bot->getHumanCharacteristics()->format();
        }

        if (empty($conversations)) {
            $replacements["<title/recentConversations>"] = "";
            $replacements["<recentConversations>"] = "";
        } else {
            $replacements["<title/recentConversations>"] = "【最近の会話内容】";
            $replacements["<recentConversations>"] = $this->convertConversationsToText($conversations);
        }

        if (empty($webSearchResults)) {
            $replacements["<title/web_search_results>"] = "";
            $replacements["<web_search_results>"] = "";
        } else {
            $replacements["<title/web_search_results>"] = "【Web検索結果】";
            $replacements["<web_search_results>"] = $webSearchResults;
        }

        $result = Messages::CHAT_CONTEXT_TEMPLATE;
        foreach ($replacements as $placeholder => $value) {
            if ($value === "") {
                // Remove placeholder and any trailing newline if the value is empty
                $result = str_replace($placeholder . "\n", "", $result);
                $result = str_replace($placeholder, "", $result);
            } else {
                $result = str_replace($placeholder, $value, $result);
            }
        }

        return $result;
    }

    /**
     * @param Conversation[] $conversations
     */
    private function convertConversationsToText(array $conversations): string
    {
        $result = "";
        foreach ($conversations as $conversation) {
            $result .= "・日時：" . $conversation->getCreatedAt()->format('Y-m-d H:i:s') . "\n";
            $speakerDisplay = ($conversation->getSpeaker() === "human") ? "話し相手" : "チャットボット（あなた）";
            $result .= "・発言者：" . $speakerDisplay . "\n";
            $result .= "・内容：" . $conversation->getContent() . "\n";
            $result .= str_repeat("-", 80) . "\n";
        }
        return $result;
    }
}
