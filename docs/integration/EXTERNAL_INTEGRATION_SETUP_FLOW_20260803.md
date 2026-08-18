# 外部システム連携セットアップ手順

作成日: 2026-08-03  
更新日: 2026-08-17  
対象: 管理者、外部開発者、連携担当者

## 1. この手順の目的

この手順は、外部サービスを代理店システムへ接続するときに「最初に何をするか」を迷わないようにするためのものです。

外部サービスの例:

- 戦国パスポート
- ショッピングサイト
- AIアート教室
- 今後追加される商品販売サイト、会員サイト、予約サイト

## 2. 基本ルール

- 外部サービス1つにつき、連携設定を1件作ります。
- 外部サービス1つにつき、代理店システムが発行するAPIキーは1つです。
- 商品やLPが複数ある場合は `project_key` で分けます。
- 外部サービスから代理店システムへ送るときは、代理店システムが発行したキーを使います。
- 代理店システムから外部サービスへ送るときは、外部サービスが発行したキーを使います。

## 3. 最初に確認するもの

| 確認項目 | 例 | 説明 |
| --- | --- | --- |
| 外部サービス名 | 戦国パスポート | 管理画面に表示する名前 |
| サイトキー | `sengoku-passport` | 連携先を識別する固定キー |
| 送信先URL | `https://example.com` | 代理店システムからWebhookを送る先 |
| 連携先発行APIキー | `sp_xxx` | 外部サービス側で発行してもらうキー |
| project_key | `sengoku-influencer` | 商品、LP、案件を識別するキー |

## 4. 管理者が行う設定

### Step 1. プロジェクトを確認する

管理画面の「プロジェクト管理」で、対象商品のスラッグを確認します。このスラッグを `project_key` として外部サービスへ伝えます。

例:

- `sengoku-influencer`
- `ai-art-school`

### Step 2. 連携ウィザードで連携先を追加する

管理画面の「連携ウィザード」で、連携先を登録します。
詳細な項目を調整したい場合は「外部API連携」を開きます。

入力する内容:

- サイトキー
- 連携先名
- 送信先URL
- 連携先が発行した受信用APIキー
- 状態
- 表示順

### Step 3. 代理店システムが発行したAPIキーを確認する

連携ウィザードで連携先を追加すると、代理店システムが発行したAPIキーと、外部開発者へ渡すAPI一覧が表示されます。
登録済みの連携先は、外部API連携の編集画面でもAPIキーを確認できます。

このキーは、外部サービスが次のAPIを呼ぶときに使います。

- `POST /api/common-users/resolve`
- `POST /api/referrals/capture`
- `POST /api/referrals/confirm`
- `POST /api/integrations/agencies`
- `POST /api/integrations/events`

### Step 4. 外部開発者へ渡す

外部開発者へ渡す情報:

- Base URL: `https://sengoku-ai.com`
- APIキー
- サイトキー
- 使用する `project_key`
- 必要なAPI一覧
- SSOが必要な場合はSSO起動URLとJWKS URL

### Step 5. 接続テストを行う

外部API連携画面の「接続テスト」を実行します。成功・失敗は「外部連携ログ」で確認します。

## 5. 外部開発者が実装するAPI

### 共通顧客ID解決

```http
POST https://sengoku-ai.com/api/common-users/resolve
```

`/api/v2/common-users/resolve` は正式な公開エンドポイントではありません。

### 紹介流入

```http
POST https://sengoku-ai.com/api/referrals/capture
```

紹介URLから外部サービスへ遷移した時点で呼びます。

### 登録・購入・申込確定

```http
POST https://sengoku-ai.com/api/referrals/confirm
```

登録、購入、申込が完了したタイミングで呼びます。

### 代理店同期

```http
POST https://sengoku-ai.com/api/integrations/agencies
```

外部サービス側で作成された代理店候補やユーザーを、代理店システムへ反映する場合に使います。

### 外部イベント送信

```http
POST https://sengoku-ai.com/api/integrations/events
```

購入、会員状態変更、予約などのイベントを代理店システムへ送る場合に使います。

## 6. 商品販売サイトでの使い方

戦国経済圏で複数商品を販売する場合、商品ごとにAPIキーを分ける必要はありません。

推奨:

- 外部サービス: 1件
- APIキー: 1つ
- 商品や案件: `project_key` と `product_code` で区別

例:

```json
{
  "service_key": "sengoku-market",
  "project_key": "sengoku-influencer",
  "product_code": "starter-pack",
  "event_type": "purchase.completed"
}
```

## 7. SSOを使う場合

SSO起動URL:

```http
https://sengoku-ai.com/agent/sso_launch.php?client={site_key}
```

JWKS:

```http
https://sengoku-ai.com/api/sso/jwks.php
```

連携先はRS256署名のJWTをJWKSで検証します。

## 8. 接続確認チェックリスト

- 外部API連携に連携先を登録した
- 代理店システム発行APIキーを外部開発者へ渡した
- 連携先発行APIキーを管理画面へ登録した
- `project_key` を決めた
- `POST /api/common-users/resolve` を呼べる
- `POST /api/referrals/capture` を呼べる
- `POST /api/referrals/confirm` を呼べる
- 接続テストが成功した
- 外部連携ログで成功を確認した

## 9. よくある間違い

### 商品ごとにAPIキーを増やしてしまう

同じ外部サービスならAPIキーは1つで問題ありません。商品やLPは `project_key` で分けます。

### `/api/v2/common-users/resolve` を使ってしまう

正式な公開パスは `/api/common-users/resolve` です。

### 内部IDを外部キーとして使ってしまう

外部連携では `agent_code` を使ってください。

### `project_key` を送らない

どの商品や案件の成果か分からなくなるため、外部サービス側では原則必ず送信してください。
