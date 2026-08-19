<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\Bot\BotRepository;
use App\Domain\Bot\Trigger\TimerTrigger;
use App\Infrastructure\DependencyInjection\Container;
use App\Infrastructure\Line\LineClient;
use App\Infrastructure\Logger\Logger;

class TriggerApplicationService
{
    private const TIMER_TRIGGERED_BY_N_MINS = 10;

    public function __construct(
        private BotRepository $botRepository,
        private LineClient $lineClient,
        private Container $container,
        private Logger $logger
    ) {
    }

    /**
     * Executes all eligible triggers for all bots.
     */
    public function executeTriggers(): void
    {
        foreach ($this->botRepository->getAllUserBots() as $botUser) {
            foreach ($botUser->getTriggers() as $trigger) {
                if (!$trigger instanceof TimerTrigger) {
                    $this->logger->log("Skipping trigger for user {$botUser->getId()} as it's not a TimerTrigger.");
                    continue;
                }

                if ($trigger->getEvent() !== TimerTrigger::EVENT_TIMER) {
                    continue;
                }

                if (!$trigger->shouldRunNow(self::TIMER_TRIGGERED_BY_N_MINS)) {
                    continue;
                }

                $this->logger->log(sprintf(
                    "Executing Trigger for bot %s: Date=%s, Time=%s, Request=%s",
                    $botUser->getId(),
                    $trigger->getDate(),
                    $trigger->getTime(),
                    $trigger->getRequest()
                ));

                try {
                    $chatApplicationService = $this->container->createChatApplicationService($botUser);
                } catch (\Exception $e) {
                    $this->logger->log("TRIGGER: Failed to initialize ChatApplicationService for user {$botUser->getId()}: " . $e->getMessage());
                    continue;
                }

                $answer = $chatApplicationService->handleTrigger($trigger)->getText();
                $this->lineClient->sendPush(
                    bot: $chatApplicationService->getLineTarget(),
                    targetId: $botUser->getId(),
                    message: $answer,
                );
            }
        }
    }
}
