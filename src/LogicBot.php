<?php

declare(strict_types=1);

namespace MyApp;

use yananob\MyTools\Utils;
use yananob\MyTools\Gpt;

class LogicBot
{
    private Gpt $gpt;

    const PROMPT_JUDGE_COMMAND = <<<EOM
メッセージが、
・回答の仕方を変えてほしい依頼だったら、1を
・あなたの特徴を変えてほしい依頼だったら、2を
・どこかの日の決めた時刻に何かをしてほしい依頼だったら、3を
・毎日決めた時刻に何かをしてほしい依頼だったら、4を
・その他だと思ったら、9を
返してください。

例：
・武士口調で返して　→　1
・悩み相談に答えて　右　1
・学校の先生になって　→　2
・武士になって　→　2
・明日の7時半にお知らせメッセージを送って　→　3
・30分後に料理ができたと教えて　→　3
・毎日朝7時におはようメッセージを送って　→　4
・あなたの趣味は？　→　9
・天気予報を教えて　→　9
EOM;

    public function __construct()
    {
        $this->gpt = new Gpt(__DIR__ . "/../configs/gpt.json");
    }

    public function judgeCommand(string $message): Command
    {
        $result = $this->gpt->getAnswer(self::PROMPT_JUDGE_COMMAND, $message);
        return Command::from($result);
    }

    public function splitOneTimeTrigger(string $message) {}
}
