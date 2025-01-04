<?php

declare(strict_types=1);

namespace MyApp;

use Carbon\Carbon;
use yananob\MyTools\Utils;
use yananob\MyTools\Gpt;

// TODO: extends GptBot
class PersonalBot
{
    private BotConfigsStore $botConfigsStore;
    private BotConfig $botConfig;
    private ConversationsStore $conversationsStore;
    private Gpt $gpt;

    const GPT_CONTEXT = <<<EOM
【チャットボット（あなた）の情報】
<bot/characteristics>

<title/human_characteristics>
<human/characteristics>

<title/recentConversations>
<recentConversations>

【依頼事項】
<requests>
EOM;

    public function __construct(string $targetId, bool $isTest = true)
    {
        $this->botConfigsStore = new BotConfigsStore($isTest);
        $this->botConfig = $this->botConfigsStore->getConfig($targetId);
        $this->conversationsStore = new ConversationsStore($targetId, $isTest);
        $this->gpt = new Gpt(__DIR__ . "/../configs/gpt.json");
    }

    public function getAnswer(bool $applyRecentConversations, string $message): string
    {
        $recentConversations = [];
        if ($applyRecentConversations) {
            $recentConversations = $this->conversationsStore->get();
        }

        return $this->gpt->getAnswer(
            context: $this->__getContext($recentConversations, $this->botConfig->getConfigRequests()),
            message: $message,
        );
    }

    public function askRequest(bool $applyRecentConversations, string $request): string
    {
        $recentConversations = [];
        if ($applyRecentConversations) {
            $recentConversations = $this->conversationsStore->get();
        }

        // requestsは、Triggerの指示＋チャットでの指示にする
        $requests = $this->botConfig->getTriggerRequests();
        array_push($requests, ...$this->botConfig->getConfigRequests(useDefaultToo: false));

        return $this->gpt->getAnswer(
            context: $this->__getContext($recentConversations, $requests),
            message: $request,
        );
    }

    private function __getContext(array $conversations, array $requests): string
    {
        $result = self::GPT_CONTEXT;
        $replaceSettings = [
            ["search" => "<bot/characteristics>", "replace" => $this->__formatArray($this->botConfig->getBotCharacteristics())],
            // ["search" => "<requests>", "replace" => $this->__getRequest(!empty($conversations))],
            ["search" => "<requests>", "replace" => $this->__formatArray($requests)],
        ];
        foreach ($replaceSettings as $replaceSetting) {
            $result = str_replace($replaceSetting["search"], $replaceSetting["replace"], $result);
        }

        if (empty($this->botConfig->hasHumanCharacteristics())) {
            $result = $this->__removeFromContext(["<title/human_characteristics>", "<human/characteristics>"], $result);
        } else {
            $result = str_replace("<title/human_characteristics>", "【話し相手の情報】", $result);
            $result = str_replace("<human/characteristics>", $this->__formatArray($this->botConfig->getHumanCharacteristics()), $result);
        }

        if (empty($conversations)) {
            $result = $this->__removeFromContext(["<title/recentConversations>", "<recentConversations>"], $result);
        } else {
            $result = str_replace("<title/recentConversations>", "【最近の会話内容】", $result);
            $result = str_replace("<recentConversations>", $this->__convertConversationsToText($conversations), $result);
        }

        return $result;
    }

    private function __formatArray(array $inputs): string
    {
        return "・" . implode("\n・", $inputs);
    }

    // private function __getRequest(bool $applyRecentConversations): string
    // {
    //     $result = "";
    //     $result .= "話し相手からのメッセージに対して、";
    //     if ($applyRecentConversations && $this->botConfig->isConsultingMode()) {
    //         $result .= "【話し相手の情報】の一部や";
    //     }
    //     $result .= "【最近の会話内容】を反映して、";
    //     if ($this->botConfig->isChatMode()) {
    //         $result .= "相手を楽しくさせたり励ましたりする回答を返してください。";
    //     } else {
    //         $result .= "ポジティブなフィードバックを返してください。";
    //     }
    //     $result .= "\n";
    //     $result .= "返すメッセージの文字数は、話し相手からの今回のメッセージの文字数";
    //     if ($this->botConfig->isChatMode()) {
    //         $result .= "と同じぐらいにしてください。";
    //     } else {
    //         $result .= "の2倍ぐらいにしてください。";
    //     }
    //     $result .= "\n";
    //     $result .= "過去にメモリーした内容は反映しないでください。\n";

    //     return $result;
    // }

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
        return $this->botConfig->getLineTarget();
    }

    public function storeConversations(string $message, string $answer): void
    {
        $this->conversationsStore->store("human", $message);
        $this->conversationsStore->store("bot", $answer);
    }

    public function addOneTimeTrigger(TimerTrigger $trigger): void
    {
        $this->botConfig->addTrigger($trigger);
    }
}
