<?php

declare(strict_types=1);

namespace MyApp;

use TargetNotDefinedException;
use yananob\mytools\Utils;
use yananob\mytools\Gpt;

class PersonalConsultant
{
    private Gpt $gpt;
    private object $config;

    const GPT_CONTEXT = <<<EOM
<bot/characteristics>

カウンセリング相手の情報：
<human/characteristics>

依頼事項：
<request>
EOM;

    public function __construct(string $targetId)
    {
        $this->gpt = new Gpt(__DIR__ . "/../configs/gpt.json");

        $config = Utils::getConfig(__DIR__ . "/../config.json");
        if (!array_key_exists($targetId, $config)) {
            throw new TargetNotDefinedException("targetId [{$targetId}] is not defined.");
        }

        $this->config = json_decode($config[$targetId], false);
    }

    public function getAnswer(string $question): string
    {
        return $this->gpt->getAnswer(
            context: $this->__getContext(),
            message: $question,
        );
    }

    private function __getContext(): string
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
        return $result;
    }

    public function getLineTarget(): string
    {
        return $this->config->bot->line_target;
    }
}
