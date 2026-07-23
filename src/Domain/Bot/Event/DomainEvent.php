<?php declare(strict_types=1);

namespace App\Domain\Bot\Event;

use DateTimeImmutable;

/**
 * すべてのドメインイベントの基本インターフェース。
 */
interface DomainEvent
{
    /**
     * イベントが発生した日時を取得します。
     */
    public function getOccurredAt(): DateTimeImmutable;

    /**
     * イベントの名前を取得します。
     */
    public function getEventName(): string;
}
