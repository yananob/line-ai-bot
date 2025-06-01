<?php declare(strict_types=1);

namespace MyApp\Domain\Bot\Service;

use yananob\MyTools\Gpt;
use MyApp\Command;
use MyApp\Domain\Bot\Trigger\TimerTrigger;

class CommandAndTriggerService
{
    private Gpt $gpt;

    const PROMPT_JUDGE_COMMAND = <<<EOM
メッセージが、
・回答の仕方を変えてほしい依頼だったら、1を
・あなたの特徴を変えてほしい依頼だったら、2を
・どこかの日の決めた時刻に何かをしてほしい依頼だったら、3を
・毎日決めた時刻に何かをしてほしい依頼だったら、4を
・何かをやめてほしい依頼だったら、5を
・あなたが何ができるのかを教えてほしい依頼だったら、8を
・その他だと思ったら、9を
返してください。

例：
・武士口調で返して　→　1
・悩み相談に答えて　→　1
・学校の先生になって　→　2
・武士になって　→　2
・明日の7時半にお知らせメッセージを送って　→　3
・30分後に料理ができたと教えて　→　3
・毎日朝7時におはようメッセージを送って　→　4
・おはようメッセージをとめて　→　5
・毎朝のメッセージをやめて　→　5
・何ができる？　→　8
・あなたの趣味は？　→　9
・天気予報を教えて　→　9
EOM;

    const PROMPT_SPLIT_ONE_TIME_TRIGGER = <<<EOM
以下のメッセージを、時刻と依頼内容に分解して。
・日付は、明記されている場合はその日付を、そうでない場合は「today」として
・時刻は、時刻が明確な場合は時刻を、今からX分後の場合は「now +X mins」として

例1：
・メッセージ：6時半に天気予報を送って
・日付：today
・時刻：06:30
・依頼内容：天気予報を送って

例2：
・メッセージ：明日の30分後に料理ができたと教えて
・日付：tomorrow
・時刻：now +30 mins
・依頼内容：料理ができたと教えて
EOM;

    const PROMPT_SPLIT_DAILY_TRIGGER = <<<EOM
以下のメッセージを、時刻と依頼内容に分解して。

例1：
・メッセージ：7時半に天気予報を送って
・日付：everyday
・時刻：07:30
・依頼内容：天気予報を送って

例2：
・メッセージ：夜の10時にお休みと送って
・日付：everyday
・時刻：22:00
・依頼内容：お休みと送って
EOM;

    public function __construct()
    {
        $this->gpt = new Gpt(getenv("OPENAI_KEY_LINE_AI_BOT"), "gpt-4.1");
    }

    public function judgeCommand(string $message): Command
    {
        $result = $this->gpt->getAnswer(self::PROMPT_JUDGE_COMMAND, $message);
        try {
            // Assuming Command::from() is a method that can handle string values.
            // If Command is an enum, this might need adjustment based on PHP version.
            // For PHP 8.1+ enums with string backing: Command::tryFrom($result) ?? Command::Other
            $commandValue = $result;
            $command = Command::from($commandValue);
        } catch (\ValueError $e) {
            $command = Command::Other; // Default to 'Other' if parsing fails
        }
        return $command;
    }

    public function generateOneTimeTrigger(string $message): TimerTrigger
    {
        return $this->__generateTimerTrigger(self::PROMPT_SPLIT_ONE_TIME_TRIGGER, $message);
    }

    public function generateDailyTrigger(string $message): TimerTrigger
    {
        return $this->__generateTimerTrigger(self::PROMPT_SPLIT_DAILY_TRIGGER, $message);
    }

    private function __generateTimerTrigger(string $prompt, string $message): TimerTrigger
    {
        $result = $this->gpt->getAnswer($prompt, $message);

        // Default values in case regex fails
        $date = "today";
        $time = "now"; 
        $request = "Could not parse request";

        $matchesDate = [];
        if (preg_match('/・日付：(.+)$/m', $result, $matchesDate)) {
            $date = rtrim($matchesDate[1]);
        }

        $matchesTime = [];
        if (preg_match('/・時刻：(.+)$/m', $result, $matchesTime)) {
            $time = rtrim($matchesTime[1]);
        }
        
        $matchesRequest = [];
        if (preg_match('/・依頼内容：(.+)$/m', $result, $matchesRequest)) {
            $request = rtrim($matchesRequest[1]);
        }

        return new TimerTrigger($date, $time, $request);
    }
}
