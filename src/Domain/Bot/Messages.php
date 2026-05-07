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

    const CHAT_CONTEXT_TEMPLATE = <<<EOM
【チャットボット（あなた）の情報】
<bot/characteristics>

<title/human_characteristics>
<human/characteristics>

<title/recentConversations>
<recentConversations>

<title/web_search_results>
<web_search_results>

【依頼事項の前提】
<requests>
EOM;
}
