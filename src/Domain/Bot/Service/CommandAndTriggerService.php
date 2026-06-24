<?php declare(strict_types=1);

namespace App\Domain\Bot\Service;

use App\Domain\Bot\ValueObject\Command;
use App\Domain\Bot\Trigger\TimerTrigger;
use App\Domain\Bot\Messages;

class CommandAndTriggerService
{
    private GptInterface $gpt;

    public function __construct(GptInterface $gpt)
    {
        $this->gpt = $gpt;
    }

    public function judgeCommand(string $message): Command
    {
        $result = trim($this->gpt->getAnswer(Messages::PROMPT_JUDGE_COMMAND, $message));
        return Command::tryFrom($result) ?? Command::Other;
    }

    public function generateOneTimeTrigger(string $message): TimerTrigger
    {
        return $this->__generateTimerTrigger(Messages::PROMPT_SPLIT_ONE_TIME_TRIGGER, $message);
    }

    public function generateDailyTrigger(string $message): TimerTrigger
    {
        return $this->__generateTimerTrigger(Messages::PROMPT_SPLIT_DAILY_TRIGGER, $message);
    }

    private function __generateTimerTrigger(string $prompt, string $message): TimerTrigger
    {
        $result = trim($this->gpt->getAnswer($prompt, $message));

        // Default values in case regex fails
        $date = "today";
        $time = "now"; 
        $request = "Could not parse request";

        $matchesDate = [];
        if (preg_match('/・日付：(.+)$/m', $result, $matchesDate)) {
            $date = trim($matchesDate[1]);
        }

        $matchesTime = [];
        if (preg_match('/・時刻：(.+)$/m', $result, $matchesTime)) {
            $time = trim($matchesTime[1]);
        }
        
        $matchesRequest = [];
        if (preg_match('/・依頼内容：(.+)$/m', $result, $matchesRequest)) {
            $request = trim($matchesRequest[1]);
        }

        return new TimerTrigger($date, $time, $request);
    }
}
