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
        return $this->generateTimerTrigger(Messages::PROMPT_SPLIT_ONE_TIME_TRIGGER, $message);
    }

    public function generateDailyTrigger(string $message): TimerTrigger
    {
        return $this->generateTimerTrigger(Messages::PROMPT_SPLIT_DAILY_TRIGGER, $message);
    }

    private function generateTimerTrigger(string $prompt, string $message): TimerTrigger
    {
        $result = trim($this->gpt->getAnswer($prompt, $message));
        return TimerTrigger::fromGptResponse($result);
    }
}
