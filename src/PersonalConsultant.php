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

カウンセリング相手の情報：
<human/characteristics>

<title/recentConversations>
<recentConversations>

依頼事項：
<request>
EOM;

    public function __construct(string $configPath, string $targetId, bool $isTest = true)
    {
        $this->gpt = new Gpt(__DIR__ . "/../configs/gpt.json");
        $this->conversations = new Conversations($targetId, $isTest);

        $config = Utils::getConfig($configPath, false);
        if (!property_exists($config, $targetId)) {
            throw new TargetNotDefinedException("targetId [{$targetId}] is not defined.");
        }
        $this->config = $config->$targetId;
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
            ["search" => "<human/characteristics>", "replace" => $this->config->human->characteristics],
            ["search" => "<request>", "replace" => $this->config->request],
        ];
        foreach ($replaceSettings as $replaceSetting) {
            $result = str_replace($replaceSetting["search"], $replaceSetting["replace"], $result);
        }

        if (empty($conversations)) {
            foreach (["<title/recentConversations>", "<recentConversations>"] as $replaceKey) {
                $result = str_replace($replaceKey, "", $result);
            }
        } else {
            $result = str_replace("<title/recentConversations>", "最近の会話内容：", $result);
            $result = str_replace(
                "<recentConversations>",
                $this->__convertConversationsToText($conversations),
                $result
            );
        }

        return $result;
    }

    private function __convertConversationsToText(array $conversations): string
    {
        $result = "";
        foreach ($conversations as $conversation) {
            $result += "・日時：" . $conversation["created_at"] . "\n";
            $result += "・発言者：" . ($conversation["by"] === "human" ? "会話相手" : "ボット（あなた）") . "\n";
            $result += "・内容：" . $conversation["content"] . "\n";
            $result += str_repeat("-", 80);
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
