<?php

declare(strict_types=1);

namespace MyApp;

use ArrayObject;
use yananob\mytools\Utils;
use yananob\mytools\Gpt;
use MyApp\TargetNotDefinedException;

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

    public function __construct(string $configPath, string $targetId)
    {
        $this->gpt = new Gpt(__DIR__ . "/../configs/gpt.json");

        $config = Utils::getConfig($configPath, false);
        if (!property_exists($config, $targetId)) {
            throw new TargetNotDefinedException("targetId [{$targetId}] is not defined.");
        }

        $this->config = $config->$targetId;
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
