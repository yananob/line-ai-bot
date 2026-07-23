<?php declare(strict_types=1);

namespace App\Domain\Conversation\ValueObject;

/**
 * 会話の話し手を表す型安全なEnum。
 */
enum Speaker: string
{
    /** 人間（ユーザー） */
    case HUMAN = 'human';

    /** ボット（AI） */
    case BOT = 'bot';
}
