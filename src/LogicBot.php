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

    const PROMPT_SPLIT_ONE_TIME_TRIGGER = <<<EOM
以下のメッセージを、時刻と依頼内容に分解して。
・日付は、明記されている場合はその日付を英語で、そうでない場合はtodayとして
・時刻は、時刻が明確な場合は時刻を、今からX分後の場合は「今＋X分」として

例1：
・メッセージ：11時半に天気予報を送って
・日付：today
・時刻：11:30
・依頼内容：天気予報を送って

例2：
・メッセージ：明日の30分後に料理ができたと教えて
・日付：tomorrow
・時刻：今＋30分
・依頼内容：料理ができたと教えて
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

    public function generateOneTimeTrigger(string $message): TimerTrigger
    {
        $result = $this->gpt->getAnswer(self::PROMPT_SPLIT_ONE_TIME_TRIGGER, $message);
        // ・日付：tomorrow
        // ・時刻：今+30分
        // ・依頼内容：料理ができたと教えて
        preg_match('/・日付：(.+)$/m', $result, $matches);
        $date = rtrim($matches[1]);
        preg_match('/・時刻：(.+)$/m', $result, $matches);
        $time = rtrim($matches[1]);
        preg_match('/・依頼内容：(.+)$/m', $result, $matches);
        $request = rtrim($matches[1]);

        return new TimerTrigger($date, $time, $request);
    }
}
