<?php

declare(strict_types=1);

namespace App\Domain\Bot;

class Messages
{
    const HELP = <<<EOM
何ができるかをお送りします。

(1)メッセージや質問に回答する
(2)最近の会話を覚える
　　※グループチャットでも使えます。

(3)指定した時間にメッセージを送る
　　例：毎日7時に、朝の挨拶メッセージを送って
　　例：1時間後に、「もう時間だよ」と教えて

(4)指定した時間のメッセージをやめる
　　例：毎朝のメッセージを止めて
EOM;

    const SYSTEM_TIMER_HINT = "【システム：タイマー実行】";

    const SYSTEM_TIMER_INSTRUCTION = self::SYSTEM_TIMER_HINT . "\n以下のユーザーからの依頼内容を、あなたの設定された性格や口調に従って今まさに実行してください。\n依頼内容：";

    const PROMPT_JUDGE_WEB_SEARCH = <<<EOM
あなたはユーザーからのメッセージを分析するアシスタントです。
ユーザーのメッセージに答えるためにWeb検索が必要かどうかを判断してください。
Web検索が必要な場合は「はい」、そうでない場合は「いいえ」とだけ答えてください。
EOM;
}
