# line-ai-bot

LINE Messaging APIを使用したAIボットアプリケーションです。Google Cloud Functions上で動作し、Firestoreを永続化層として使用します。

## 機能内容

1.  **LINE Bot機能**
    - ユーザーからのテキストメッセージに対して、GPT-4o（デフォルト）を使用して応答を生成します。
    - LINEのクイックリプライを使用して、タイマートリガーの設定などのアクションを促します。
    - ウェブ検索が必要と判断された場合、OpenAIのウェブ検索ツール（gpt-5-miniなど）を使用して情報を取得し、回答に反映させます。

2.  **スケジューリング機能（タイマートリガー）**
    - 特定の日時にボットからメッセージを送信するように予約できます。
    - Cloud Scheduler等の外部トリガーにより `main_event` が呼び出され、期限が来たトリガーが実行されます。

3.  **設定エディタ（Web CRUD）**
    - `/config` エンドポイント（環境により `/{関数名}/config`）にて、Firestore上のボット設定およびトリガーを管理できる管理画面を提供します。
    - 各ボットのメイン設定（JSON）の編集、およびタイマートリガーの一覧表示・追加・編集・削除が可能です。
    - テンプレートエンジンにBladeOne、スタイルにBootstrap 5を使用しています。

## 実装方針

### 構成レイヤー
ドメイン駆動設計（DDD）の考え方を取り入れたレイヤー構造を採用しています。

- **Domain**: ボットのエンティティ、バリューオブジェクト、リポジトリインターフェース、ドメインサービス（プロンプト生成、コマンド判定など）を定義します。
- **Application**: 外部からの入力（LINE Webhook、HTTPリクエスト）を処理し、ドメイン層を調整して結果を返却するアプリケーションサービスを配置します。
- **Infrastructure**: Firestoreへの保存、OpenAI APIとの通信、LINE Messaging APIのクライアントなど、外部技術に依存する具体的な実装を配置します。

### 技術スタック
- **Language**: PHP 8.3/8.4
- **Framework**: Google Cloud Functions Framework for PHP
- **Database**: Google Cloud Firestore
- **AI Integration**: OpenAI API (GPT-4o, Web Search)
- **Messaging**: LINE Messaging API
- **View**: BladeOne, Bootstrap 5
- **DI**: 独自実装の `Container` クラスによる依存注入

### エントリポイント
- `index.php`: `main_http` (HTTPリクエスト処理) と `main_event` (CloudEvent/タイマー処理) のエントリポイントです。

## Julesへの指示
- タスクを行う前に、AGENTS.mdを参照して、指示に従ってください。

## file_structure.txt について
`file_structure.txt` ファイルは、プロジェクトのファイル構造のスナップショットです。このファイルは `ls -R` コマンドの出力を保存しており、プロジェクト内のファイルとディレクトリの構成を理解するのに役立ちます。
