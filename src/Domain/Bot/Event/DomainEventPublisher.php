<?php declare(strict_types=1);

namespace App\Domain\Bot\Event;

/**
 * ドメインイベントの登録と配信を管理するパブリッシャー（シングルトン）。
 */
class DomainEventPublisher
{
    private static ?self $instance = null;

    /**
     * イベント購読者のリスト
     * @var array<string, array<callable>>
     */
    private array $subscribers = [];

    private function __construct()
    {
    }

    /**
     * シングルトンインスタンスを取得します。
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * シングルトンインスタンスを明示的に設定またはクリアします（テスト用）。
     */
    public static function setInstance(?self $instance): void
    {
        self::$instance = $instance;
    }

    /**
     * 特定のイベントクラス、またはすべてのイベント（"*"）に対するサブスクライバーを登録します。
     *
     * @param string $eventClass イベントクラスの完全修飾名、または '*'
     * @param callable $subscriber function(DomainEvent $event): void 形式のコールバック
     */
    public function subscribe(string $eventClass, callable $subscriber): void
    {
        $this->subscribers[$eventClass][] = $subscriber;
    }

    /**
     * イベントをパブリッシュし、登録されているすべての購読者に通知します。
     */
    public function publish(DomainEvent $event): void
    {
        $eventClass = get_class($event);

        // 1. 特定のイベントに対する購読者の通知
        if (isset($this->subscribers[$eventClass])) {
            foreach ($this->subscribers[$eventClass] as $subscriber) {
                $subscriber($event);
            }
        }

        // 2. すべてのイベント（ワイルドカード）に対する購読者の通知
        if (isset($this->subscribers['*'])) {
            foreach ($this->subscribers['*'] as $subscriber) {
                $subscriber($event);
            }
        }
    }

    /**
     * 登録されているすべてのサブスクライバーをクリアします（テスト用）。
     */
    public function clear(): void
    {
        $this->subscribers = [];
    }
}
