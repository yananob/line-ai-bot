<?php

declare(strict_types=1);

namespace MyApp;

use ArrayObject;
use yananob\MyTools\Utils;
use yananob\MyTools\Gpt;
use MyApp\TargetNotDefinedException;

class PersonalConsultant
{
    private Gpt $gpt;
    private object $config;
    private Conversations $conversations;

    const GPT_CONTEXT = <<<EOM
<bot/characteristics>

<title/human_characteristics>
<human/characteristics>

<title/recentConversations>
<recentConversations>

【依頼事項】
<request>
EOM;

    public function __construct(string $configPath, string $targetId, bool $isTest = true)
    {
        $this->gpt = new Gpt(__DIR__ . "/../configs/gpt.json");
        $this->conversations = new Conversations($targetId, $isTest);

        $config = Utils::getConfig($configPath, false);
        if (property_exists($config, $targetId)) {
            $this->config = $config->$targetId;
        } else {
            $this->config = $config->default;
        }
    }

    public function getAnswer(bool $applyRecentConversations, string $message): string
    {
        $recentConversations = [];
        if ($applyRecentConversations) {
            $recentConversations = $this->conversations->get();
        }

        return $this->gpt->getAnswer(
            context: $this->__getContext($recentConversations),
            message: $message,
        );
    }

    private function __getContext(array $conversations): string
    {
        $result = self::GPT_CONTEXT;
        $replaceSettings = [
            ["search" => "<bot/characteristics>", "replace" => $this->config->bot->characteristics],
            ["search" => "<request>", "replace" => $this->config->request],
        ];
        foreach ($replaceSettings as $replaceSetting) {
            $result = str_replace($replaceSetting["search"], $replaceSetting["replace"], $result);
        }

        if (empty($this->config->human)) {
            $result = $this->__removeFromContext(["<title/human_characteristics>", "<human/characteristics>"], $result);
        } else {
            $result = str_replace("<title/human_characteristics>", "【話し相手の情報】", $result);
            $result = str_replace("<human/characteristics>", $this->config->human->characteristics, $result);
        }

        if (empty($conversations)) {
            $result = $this->__removeFromContext(["<title/recentConversations>", "<recentConversations>"], $result);
        } else {
            $result = str_replace("<title/recentConversations>", "【最近の会話内容】", $result);
            $result = str_replace("<recentConversations>", $this->__convertConversationsToText($conversations), $result);
        }

        return $result;
    }

    private function __removeFromContext(array $keywords, string $source): string
    {
        foreach ($keywords as $keyword) {
            $source = str_replace($keyword . "\n", "", $source);
        }
        return $source;
    }

    private function __convertConversationsToText(array $conversations): string
    {
        $result = "";
        foreach ($conversations as $conversation) {
            $result .= "・日時：" . $conversation->created_at . "\n";
            $result .= "・発言者：" . ($conversation->by === "human" ? "話し相手" : "チャットボット（あなた）") . "\n";
            $result .= "・内容：" . $conversation->content . "\n";
            $result .= str_repeat("-", 80) . "\n";
        }
        return $result;
    }

    public function getLineTarget(): string
    {
        return $this->config->bot->line_target;
    }

    public function storeConversations(string $message, string $answer): void
    {
        $this->conversations->store("human", $message);
        $this->conversations->store("bot", $answer);
    }
}
