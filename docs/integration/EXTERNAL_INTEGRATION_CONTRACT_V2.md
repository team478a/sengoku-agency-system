# 千ノ国 代理店システム 外部連携契約 v2

作成日: 2026-08-03  
対象: sengoku-ai.com 代理店システム  
ステータス: PR-S01 契約固定版

この文書は、外部システム連携をシンプルにするための正式な責任分界・用語・API分類を定義します。既存APIを直ちに廃止するものではありません。実装変更は、この契約に沿って段階的に行います。

## 1. 基本方針

代理店システムは、代理店構造と紹介関係のハブです。外部システムは、各サービス固有の商品・注文・決済・権利付与を管理します。

代理店システムが責任を持つ情報:

- `common_user_id`
- 代理店階層
- 紹介者、担当代理店、継承関係
- 紹介URL、流入セッション、成果確定
- 代理店報酬の計算根拠
- 代理店・管理スタッフ向けSSO発行

外部システムが責任を持つ情報:

- 商品、講座、NFT、ガチャ、パスポートなどのサービス固有データ
- 注文、決済、返金、キャンセル
- 外部サービス内の会員アカウント
- サービス利用権、視聴権、参加権
- 外部サービス内の画面・操作ログ

## 2. 用語

| 用語 | 意味 | 備考 |
| --- | --- | --- |
| `system_key` | 外部システムを識別するキー | 例: `sengoku-passport`, `ai-art-school` |
| `site_key` | 管理画面で登録する連携先サイトキー | 原則 `system_key` と同じ値を使う |
| `project_key` | 代理店システム内の案件・プロジェクト識別子 | 例: `sengoku-influencer`, `ai-art-school` |
| `product_code` | 外部システム側の商品・プラン識別子 | 同一システム内で商品が複数ある場合に使う |
| `common_user_id` | 千ノ国共通顧客ID | 代理店システムが発行・管理する |
| `agent_code` | 代理店の公開識別子 | 内部DBの `id` ではなくこちらを外部連携で使う |
| `service_user_id` | 外部システム側のユーザーID | 各外部システムが発行する |
| `referral_session_key` | 紹介流入セッション | `capture` で発行し、`confirm` で成果確定に使う |

互換のため、既存の `service_key`、`client_key`、`client`、`project_slug` は当面受け付けます。ただし、新規ドキュメントでは `system_key`、`site_key`、`project_key` に統一します。

## 3. APIの分類

### 3.1 外部システムから代理店システムへ送るAPI

外部システムが、ユーザー登録・購入・申込・決済完了などを代理店システムへ通知するAPIです。

| 用途 | エンドポイント | 状態 |
| --- | --- | --- |
| 共通顧客IDの解決 | `POST /api/common-users/resolve` | 現行 |
| 紹介流入の記録 | `POST /api/referrals/capture` | 現行 |
| 紹介成果の確定 | `POST /api/referrals/confirm` | 現行 |
| 汎用イベント受信 | `POST /api/integrations/events` | PR-S02で追加 |
| 代理店階層取得 | `GET /api/hierarchy.php` | 現行 |

`/api/v2/common-users/resolve` は現時点の正式パスではありません。正式パスは `/api/common-users/resolve` です。

### 3.2 代理店システムから外部システムへ送るAPI

代理店システムが、代理店情報や共通顧客イベントを外部システムへ通知するAPIです。

| 用途 | 送信先 | 内容 |
| --- | --- | --- |
| 代理店マスタ同期 | 連携先ごとの agency sync endpoint | 代理店登録・更新・停止・削除 |
| 共通イベント通知 | 連携先ごとの common event endpoint | 共通顧客ID統合、担当代理店変更、成果確定など |
| SSO起動 | 連携先ごとの SSO endpoint | 代理店・スタッフを外部ポータルへログインさせる |

今後は、代理店マスタ同期と共通イベント通知を分けます。

- `agency sync endpoint`: 代理店マスタ専用
- `common event endpoint`: ユーザー・成果・問い合わせなどの共通イベント専用

## 4. `/api/integrations/agencies` と `/api/integrations/events` の役割

`/api/integrations/agencies` は代理店マスタ同期専用です。以下のようなイベントだけを扱います。

- `agency.created`
- `agency.updated`
- `agency.suspended`
- `agency.deleted`
- `connection_test`

`/api/integrations/events` は共通イベント受信用です。以下のようなイベントを扱います。

- `user.registered`
- `lead.created`
- `inquiry.created`
- `application.completed`
- `order.created`
- `order.completed`
- `payment.succeeded`
- `payment.failed`
- `payment.refunded`
- `entitlement.granted`
- `entitlement.revoked`
- `attendance.confirmed`
- `attendance.cancelled`

既存互換のため、当面は古い `/api/integrations/agencies` にイベントが届く構成も残します。ただし、新規連携先は `/api/integrations/events` を使う前提で実装します。

PR-S02時点では、`/api/integrations/events` は受信・認証・重複防止・ログ保存・注文系イベントの `customer_transactions` 保存まで対応します。報酬再計算や既存報酬データの上書きは行いません。

## 5. イベント共通形式

外部システムから代理店システムへ送るイベントは、以下の形式を基本にします。

```json
{
  "event": "order.completed",
  "system_key": "sengoku-passport",
  "project_key": "sengoku-influencer",
  "product_code": "passport-standard",
  "occurred_at": "2026-08-03T10:00:00+09:00",
  "idempotency_key": "sengoku-passport:order:12345:completed",
  "common_user_id": "cu_...",
  "service_user_id": "12345",
  "agent_code": "agent_7_8573",
  "referral_session_key": "rs_...",
  "payload": {
    "order_id": "12345",
    "amount": 9800,
    "currency": "JPY"
  }
}
```

必須項目:

- `event`
- `system_key`
- `occurred_at`
- `idempotency_key`

状況により必要な項目:

- `common_user_id`: 共通顧客IDが解決済みの場合
- `service_user_id`: 外部システム側ユーザーが存在する場合
- `agent_code`: 紹介者または担当代理店が判明している場合
- `project_key`: 案件・プロジェクト単位で成果を分ける場合
- `product_code`: 商品・プラン単位で成果を分ける場合
- `referral_session_key`: 紹介流入から成果確定する場合

## 6. 認証

### 6.1 外部システムから代理店システムへ送る場合

現行の正式認証は、連携先ごとに代理店システムが発行する受信用APIキーです。

利用可能なヘッダー:

```http
x-api-key: {AI側が発行した連携先別APIキー}
```

または:

```http
Authorization: Bearer {AI側が発行した連携先別APIキー}
```

### 6.2 代理店システムから外部システムへ送る場合

連携先が発行した受信用APIキーを、代理店システムに登録します。代理店システムは外部システムへ送信する際にそのキーを使います。

基本ヘッダー:

```http
x-api-key: {外部システム側が発行したAPIキー}
Authorization: Bearer {外部システム側が発行したAPIキー}
```

HMAC署名は拡張認証です。連携先が対応する場合のみ利用します。

```http
X-SenNoKuni-Key-Id: {key_id}
X-SenNoKuni-Timestamp: {unix_timestamp}
X-SenNoKuni-Nonce: {random_nonce}
X-SenNoKuni-Signature: {hmac_sha256}
```

署名対象:

```text
timestamp + "\n" + nonce + "\n" + raw_body
```

署名アルゴリズム:

```text
HMAC-SHA256
```

## 7. 共通顧客ID

`POST /api/common-users/resolve` は、外部システム側ユーザーと `common_user_id` を紐づけます。

正式パス:

```http
POST /api/common-users/resolve
```

基本リクエスト:

```json
{
  "system_key": "sengoku-passport",
  "service_user_id": "12345",
  "email": "user@example.com",
  "display_name": "山田 太郎",
  "wallet_address": "0x...",
  "metadata": {
    "source": "signup"
  }
}
```

`display_name` が正式名です。既存互換として `name` も受け付ける方針です。`wallet_address` と `metadata` は任意です。

## 8. 紹介流入と成果確定

### 8.1 `referrals/capture`

紹介URLから外部システムへ遷移した時点で、流入を記録します。

```http
POST /api/referrals/capture
```

`capture` は継続利用します。廃止予定はありません。

### 8.2 `referrals/confirm`

登録・購入・申込など、成果が確定した時点で呼びます。

```http
POST /api/referrals/confirm
```

`confirm` は初回の紹介関係・成果確定に使います。購入や決済が複数回発生するシステムでは、2回目以降の注文・決済イベントは `/api/integrations/events` に送ります。

## 9. `project_key` と `product_code`

`project_key` は代理店システム側の案件識別子です。

例:

- `sengoku-influencer`
- `ai-art-school`

`product_code` は外部システム側の商品・プラン識別子です。

例:

- `passport-standard`
- `ai-art-trial`
- `market-course-basic`

原則:

- 代理店LPや案件単位で分けたいものは `project_key`
- 外部サービス内の商品単位で分けたいものは `product_code`
- 戦国経済圏の商品販売のように商品が増える場合、外部サービス側は商品ごとに `product_code` を送る
- 代理店システム側は `system_key + project_key + product_code` で成果分類できるようにする

既存互換として `project_slug` も受け付けますが、今後の正式名は `project_key` です。

## 10. SSO

SSOは、代理店・管理スタッフが外部ポータルへ移動するためのログイン連携です。

現行スコープ:

- エージェント
- ディレクター
- アドバイザー
- スーパーアドバイザー
- インフルエンサー
- 管理スタッフ

一般購入者・一般会員のSSOは将来フェーズです。一般会員の共通ID連携は `common_user_id` で行い、SSOとは分けます。

JWT署名方式:

- 署名アルゴリズム: `RS256`
- 公開鍵配布: `JWKS`
- `iss`: `https://sengoku-ai.com`
- `aud`: 連携先の `site_key`
- `sub`: 代理店またはスタッフの識別子
- `exp`: 有効期限
- `iat`: 発行日時
- `jti`: リプレイ防止用ID

SSO起動時のURLパラメータ名は既存互換として `client` を使いますが、値は `site_key` です。

```http
GET /agent/sso_launch.php?client=sengoku-passport
```

## 11. 階層取得API

代理店階層は、外部システムが参照用に取得できます。

```http
GET /api/hierarchy.php?format=tree&include_contact=1
```

外部連携で使う識別子は `agent_code` です。内部DBの `id` は外部システムの永続キーに使わないでください。

`format=tree` の場合は、子代理店を `children` 配列に入れます。子がいない場合は `children: []` を返す方針にします。

## 12. 移行方針

既存連携を壊さないため、以下の順番で移行します。

1. 契約文書を固定する
2. `/api/integrations/events` を追加する
3. 送信先を agency sync endpoint と common event endpoint に分離する
4. 管理画面で連携先ごとのキー・URLを分かりやすく設定できるようにする
5. 接続テストを agency sync と common event に分ける
6. 外部開発者向けドキュメントを自動生成または最新版に統一する

## 13. 後方互換

当面維持するもの:

- `POST /api/common-users/resolve`
- `POST /api/referrals/capture`
- `POST /api/referrals/confirm`
- `GET /api/hierarchy.php`
- `x-api-key`
- `Authorization: Bearer`
- `project_slug`
- `service_key`
- `client` URLパラメータ

今後の正式名:

- `system_key`
- `site_key`
- `project_key`
- `product_code`
- `common_event_endpoint`
- `agency_sync_endpoint`

## 14. 実装時の注意

- 既存API、既存代理店管理、既存報酬処理を壊さない
- 報酬確定済みデータを再計算・上書きしない
- `Idempotency-Key` または `idempotency_key` で二重登録を防ぐ
- 外部送信は失敗しても画面操作を止めず、outboxで再送できる構成にする
- APIキーやHMACシークレットは画面で常時露出しない
- 外部システムごとに受信用キーと送信用キーを分ける
