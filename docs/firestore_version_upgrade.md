# google/cloud-firestore バージョンアップ手順書 (v1.x -> v2.x)

本ドキュメントでは、`google/cloud-firestore` の Major Version Upgrade（v1.x から v2.3.1 以降へ）を他のリポジトリで実施する際の手順、影響範囲、および注意点についてまとめています。

---

## 1. 概要と変更点

`google/cloud-firestore` v2.x へのバージョンアップにおける主な変更点・注意点は以下の通りです。

1. **PHP 拡張モジュール `ext-grpc` / `ext-protobuf` の要求**
   - v2.x では、PHPの `grpc` 拡張モジュール（および `protobuf` 拡張）が要求されます。
   - CLI環境で `grpc` 拡張が無効な場合、`composer require` や `composer update` 実行時に `ext-grpc is missing from your system` とエラーが発生する可能性があります。
   - CLI実行時等でプラットフォーム要件をバイパスする必要がある場合は `--ignore-platform-req=ext-grpc` オプションを利用します。

2. **返り値の型の厳格化（`ItemIterator` 化など）**
   - `DocumentReference::collections()` などのコレクション一覧取得メソッドの返り値の型宣言が、`array` から `Google\Cloud\Core\Iterator\ItemIterator`（`\Iterator` の実装）に変更されました。
   - これに伴い、`collections()` の戻り値を配列前提でモック（PHPUnit）していたテストコードは修正が必要です。

3. **FirestoreClient の初期化と環境変数の見直し**
   - v2 移行に伴い、`FirestoreClient` の初期化は `new FirestoreClient()` （引数なし）で実施し、Application Default Credentials (ADC) や GCP 環境のデフォルト認証を利用します。
   - 明示的に `FIREBASE_SERVICE_ACCOUNT` キーファイルを渡す初期化処理および環境変数が不要となります。

4. **削除されたメソッド・インターフェースの更新**
   - v1 から v2 への移行に伴い非推奨・削除されたメソッド（例: `FirestoreClient::batch()` 等の古いインターフェース）がモックや本実装に残っていないか確認してください。

---

## 2. バージョンアップ手順

### ステップ 1: `composer.json` の更新と依存関係アップデート

`composer.json` の `require` セクションで `google/cloud-firestore` のバージョンを変更します。

```json
"require": {
    "google/cloud-firestore": "^2.3.1"
}
```

コマンドラインで更新を実行します（`grpc` 拡張モジュールに関するエラーが出る場合は `--ignore-platform-req=ext-grpc` を付与）：

```bash
composer update google/cloud-firestore --with-all-dependencies --ignore-platform-req=ext-grpc
```

### ステップ 2: `FirestoreClient` 初期化処理の修正

リポジトリクラス等の初期化処理から `keyFile` の指定（および `FIREBASE_SERVICE_ACCOUNT` 環境変数への依存）を削除し、引数なしでインスタンス化するように変更します。

```php
// 変更前
$this->db = $db ?? new FirestoreClient(["keyFile" => json_decode(getenv("FIREBASE_SERVICE_ACCOUNT") ?: '[]', true)]);

// 変更後
$this->db = $db ?? new FirestoreClient();
```

---

## 3. テスト（PHPUnit）修正時のポイント

### ポイント 1: `collections()` のモック作成

`DocumentReference::collections()` などのメソッドは `Google\Cloud\Core\Iterator\ItemIterator` を返します。

**誤ったモック例（v1.x 時代）:**
```php
// v1.x では配列を返せていた
$this->documentRootMock->method('collections')->willReturn([$botCollMock1, $botCollMock2]);
```

**正解（v2.x 向け）:**
`ItemIterator` は `\Iterator` を実装し、内部ページイテレータを受け取るコンストラクタを持っています。実際の `ItemIterator` インスタンスを生成して渡すか、モックオブジェクトを作成します。

```php
$pageIterator = new \ArrayIterator([[$botCollMock1, $botCollMock2]]);
$itemIterator = new \Google\Cloud\Core\Iterator\ItemIterator($pageIterator);

$this->documentRootMock->method('collections')->willReturn($itemIterator);
```

### ポイント 2: 未使用または存在しないメソッドへの期待値設定の削除

存在しないメソッド（例: `FirestoreClient::batch` など）に対して `expects($this->never())->method('batch')` などの設定が残っている場合、PHPUnit 10+ では `MethodCannotBeConfiguredException` が発生します。不要なメソッド設定は削除してください。

---

## 4. 動作確認チェックリスト

1. **Composer依存関係チェック**: `composer check-platform-reqs` または `composer update` が正常に完了すること。
2. **静的解析**: `vendor/bin/phpstan analyse` でエラーが発生しないこと。
3. **ユニットテスト**: `vendor/bin/phpunit` で全テストが通過すること。
