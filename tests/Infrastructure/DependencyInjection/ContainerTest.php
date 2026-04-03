<?php

declare(strict_types=1);

namespace Tests\Infrastructure\DependencyInjection;

use App\Infrastructure\DependencyInjection\Container;
use App\Application\ChatApplicationService;
use App\Domain\Bot\Bot;
use App\Domain\Bot\BotRepository;
use App\Domain\Conversation\ConversationRepository;
use App\Domain\Bot\Service\ChatPromptService;
use App\Domain\Bot\Service\CommandAndTriggerService;
use App\Domain\Bot\Service\GptInterface;
use App\Infrastructure\Line\LineClient;
use App\Infrastructure\Persistence\Firestore\FirestoreBotRepository;
use App\Infrastructure\Persistence\Firestore\FirestoreConversationRepository;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Google\Cloud\Firestore\FirestoreClient;
use Google\Cloud\Firestore\CollectionReference;
use Google\Cloud\Firestore\DocumentReference;

final class ContainerTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        // 外部依存を避けるため、環境変数を設定
        putenv("OPENAI_KEY_LINE_AI_BOT=dummy-key");
        putenv('LINE_TOKENS_N_TARGETS={"tokens": {"test": "token"}, "target_ids": {"test": "id"}}');

        $this->container = new Container();

        // Mock Firestore dependencies to avoid gRPC error
        $firestoreClientMock = $this->createMock(FirestoreClient::class);
        $collectionMock = $this->createMock(CollectionReference::class);
        $documentMock = $this->createMock(DocumentReference::class);

        $firestoreClientMock->method('collection')->willReturn($collectionMock);
        $collectionMock->method('document')->willReturn($documentMock);

        $botRepo = new FirestoreBotRepository($firestoreClientMock);
        $convRepo = new FirestoreConversationRepository($firestoreClientMock);

        $reflection = new ReflectionClass($this->container);

        $botRepoProp = $reflection->getProperty('botRepository');
        $botRepoProp->setAccessible(true);
        $botRepoProp->setValue($this->container, $botRepo);

        $convRepoProp = $reflection->getProperty('conversationRepository');
        $convRepoProp->setAccessible(true);
        $convRepoProp->setValue($this->container, $convRepo);
    }

    public function test_getBotRepository_returns_singleton(): void
    {
        $repo1 = $this->container->getBotRepository();
        $repo2 = $this->container->getBotRepository();

        $this->assertInstanceOf(BotRepository::class, $repo1);
        $this->assertSame($repo1, $repo2);
    }

    public function test_getConversationRepository_returns_singleton(): void
    {
        $repo1 = $this->container->getConversationRepository();
        $repo2 = $this->container->getConversationRepository();

        $this->assertInstanceOf(ConversationRepository::class, $repo1);
        $this->assertSame($repo1, $repo2);
    }

    public function test_getChatPromptService_returns_singleton(): void
    {
        $service1 = $this->container->getChatPromptService();
        $service2 = $this->container->getChatPromptService();

        $this->assertInstanceOf(ChatPromptService::class, $service1);
        $this->assertSame($service1, $service2);
    }

    public function test_getGptClient_returns_singleton(): void
    {
        $client1 = $this->container->getGptClient();
        $client2 = $this->container->getGptClient();

        $this->assertInstanceOf(GptInterface::class, $client1);
        $this->assertSame($client1, $client2);
    }

    public function test_getCommandAndTriggerService_returns_singleton(): void
    {
        $service1 = $this->container->getCommandAndTriggerService();
        $service2 = $this->container->getCommandAndTriggerService();

        $this->assertInstanceOf(CommandAndTriggerService::class, $service1);
        $this->assertSame($service1, $service2);
    }

    public function test_getLineClient_returns_singleton(): void
    {
        $client1 = $this->container->getLineClient();
        $client2 = $this->container->getLineClient();

        $this->assertInstanceOf(LineClient::class, $client1);
        $this->assertSame($client1, $client2);
    }

    public function test_createChatApplicationService_returns_instance(): void
    {
        $bot = new Bot("test-bot");
        $service = $this->container->createChatApplicationService($bot);

        $this->assertInstanceOf(ChatApplicationService::class, $service);
    }
}
