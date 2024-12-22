<?php

declare(strict_types=1);

use Google\Cloud\Firestore\FirestoreClient;
use MyApp\BotConfig;

final class BotConfigTest extends PHPUnit\Framework\TestCase
{
    private BotConfig $botConfig;

    protected function setUp(): void
    {
        $dbAccessor = new FirestoreClient(["keyFilePath" => __DIR__ . '/../configs/firebase.json']);
        $documentRoot = $dbAccessor->collection("ai-bot-test")->document("configs");
        $this->botConfig = new BotConfig($documentRoot->collection("TARGET_ID_AUTOTEST"), null);
    }

    public function testGetBotCharacteristics_withoutDefault() {}
    public function testGetBotCharacteristics_withDefault() {}
    public function testGetHumanCharacteristics() {}
    public function testHasHumanCharacteristics() {}
    public function testGetRequests() {}
    public function testGetMode() {}
    public function testIsChatMode() {}
    public function testIsConsultingMode() {}
    public function testGetLineTarget() {}
}
