<?php declare(strict_types=1);

namespace MyApp\Domain\Bot\Service;

use MyApp\Domain\Bot\Bot;
use MyApp\Domain\Conversation\Conversation;
use MyApp\Domain\Bot\ValueObject\StringList;

class ChatPromptService
{
    const GPT_CONTEXT = <<<EOM
【チャットボット（あなた）の情報】
<bot/characteristics>

<title/human_characteristics>
<human/characteristics>

<title/recentConversations>
<recentConversations>

<title/web_search_results>
<web_search_results>

【依頼事項の前提】
<requests>
EOM;

    /**
     * @param Bot $bot
     * @param Conversation[] $conversations Array of Conversation entities
     * @param StringList $requests Bot's configured requests
     * @param string|null $webSearchResults
     * @return string
     */
    public function generateContext(Bot $bot, array $conversations, StringList $requests, ?string $webSearchResults = null): string
    {
        $result = self::GPT_CONTEXT;
        $replaceSettings = [
            ["search" => "<bot/characteristics>", "replace" => $bot->getBotCharacteristics()->format()],
            ["search" => "<requests>", "replace" => $requests->format()],
        ];
        foreach ($replaceSettings as $replaceSetting) {
            $result = str_replace($replaceSetting["search"], $replaceSetting["replace"], $result);
        }

        if (empty($bot->hasHumanCharacteristics())) {
            $result = $this->removeFromContext(["<title/human_characteristics>", "<human/characteristics>"], $result);
        } else {
            $result = str_replace("<title/human_characteristics>", "【話し相手の情報】", $result);
            $result = str_replace("<human/characteristics>", $bot->getHumanCharacteristics()->format(), $result);
        }

        if (empty($conversations)) {
            $result = $this->removeFromContext(["<title/recentConversations>", "<recentConversations>"], $result);
        } else {
            $result = str_replace("<title/recentConversations>", "【最近の会話内容】", $result);
            $result = str_replace("<recentConversations>", $this->convertConversationsToText($conversations), $result);
        }

        if (empty($webSearchResults)) {
            $result = $this->removeFromContext(["<title/web_search_results>", "<web_search_results>"], $result);
        } else {
            $result = str_replace("<title/web_search_results>", "【Web検索結果】", $result);
            $result = str_replace("<web_search_results>", $webSearchResults, $result);
        }

        return $result;
    }

    private function removeFromContext(array $keywords, string $source): string
    {
        foreach ($keywords as $keyword) {
            $source = str_replace($keyword . "\n", "", $source);
            $source = str_replace($keyword, "", $source);
        }
        return $source;
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
