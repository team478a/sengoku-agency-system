# 千ノ国 代理店システム 共通顧客HUB 外部連携契約

作成日: 2026-07-21  
対象: `sengoku-ai.com` 代理店システム  
位置づけ: 外部システムが代理店システムを共通顧客HUBとして利用するための実装契約

## 1. この文書の目的

代理店システムは、千ノ国プロジェクト全体で以下の正本を担当します。

- 共通顧客ID `common_user_id`
- 代理店公開ID `agency_id`
- 代理店階層
- 紹介トークンと紹介確定
- 代理店向けSSOの発行元

外部システムは、ユーザー登録、ログイン、購入、問い合わせ、権利付与などのタイミングで、必要な情報を代理店システムへ送信します。代理店システムは、同一人物、紹介元、担当代理店、販売担当、クロージング担当を管理できる状態にします。

## 2. 絶対に混同しないID

| 項目 | 用途 | 発行元 | 外部システムで保存するか |
|---|---|---|---:|
| `common_user_id` | 顧客・会員の共通ID | 代理店システム | はい |
| `agency_id` | 代理店の公開ID | 代理店システム | はい |
| `agent_code` | `agency_id` と同じ値 | 代理店システム | 互換用に保存可 |
| `agents.id` | 代理店システム内部ID | 代理店システム | 原則保存しない |
| `service_user_id` | 外部システム側のユーザーID | 外部システム | はい |
| `referral_token` | 紹介・流入の正規トークン | 代理店システム | 流入時に保存推奨 |

外部連携で代理店を一意に識別する値は `agency_id` です。値は `agents.agent_code` と同じです。内部DBの `agents.id` は、移行や再構築で変わる可能性があるため、外部システムの永続キーに使わないでください。

## 3. 共通顧客IDの契約

`common_user_id` は代理店システムだけが発行します。

```text
形式: cu_ + 32桁hex
例: cu_9f2c0f2e7a8b4d5a9b1c2d3e4f5a6b7c
```

外部システム側では、ローカルユーザーIDとは別のカラムとして保存してください。外部システムが独自に `common_user_id` を発行してはいけません。

## 4. 自動名寄せの安全条件

代理店システムは、未検証のメール、電話、ウォレットアドレスだけでは自動統合しません。

自動一致に使えるもの:

- 既存の `common_user_id`
- `system_key + service_user_id`
- 検証済みLINE ID
- 検証済みメール
- 検証済み電話
- 署名検証済みウォレットアドレス

未検証情報が一致した場合は、候補として扱い、自動統合しません。外部システム側も、未検証メールだけで同一人物確定をしないでください。

## 5. APIキーの契約

APIキーは接続先ごと、通信方向ごとに分けます。

| キー | 発行元 | 使う方向 | 設定場所 |
|---|---|---|---|
| AI受信用APIキー | 代理店システム | 外部システムからAIへ送信 | 外部システム側に設定 |
| 外部システム受信用APIキー | 外部システム | AIから外部システムへ送信 | 代理店システム「外部API連携」に設定 |

外部システムから代理店システムへ送る場合:

```http
x-api-key: {AI受信用APIキー}
```

または:

```http
Authorization: Bearer {AI受信用APIキー}
```

代理店システムから外部システムへ送るWebhookでは、外部システムが発行した受信用APIキーを `x-api-key` と `Authorization: Bearer` に付与します。

## 6. HMAC署名

代理店システムから外部システムへ送信するWebhookは、設定がある場合にHMAC署名を付与します。

```http
X-SenNoKuni-Key-Id: {hmac_key_id}
X-SenNoKuni-Timestamp: {unix_timestamp}
X-SenNoKuni-Nonce: {random_nonce}
X-SenNoKuni-Signature: sha256={hex_signature}
```

署名対象:

```text
timestamp + "\n" + nonce + "\n" + raw_json_body
```

アルゴリズム:

```text
HMAC-SHA256
```

外部システム側では、タイムスタンプの許容範囲、nonceの再利用禁止、署名一致を確認してください。

## 7. 主要API

### 7.1 共通顧客ID解決

```http
POST /api/common-users/resolve
```

用途:

- 外部システムで新規会員登録された
- 外部システムで既存ユーザーがログインした
- LINE、メール、ウォレットなどから共通IDを解決したい

最小リクエスト:

```json
{
  "system_key": "ai-art-school",
  "service_user_id": "local_user_123",
  "email": "user@example.com",
  "email_verified": true,
  "create_if_missing": true
}
```

代表レスポンス:

```json
{
  "ok": true,
  "common_user_id": "cu_9f2c0f2e7a8b4d5a9b1c2d3e4f5a6b7c",
  "created": true,
  "matched_by": "created",
  "identity_match_status": "ok"
}
```

### 7.2 代理店階層取得

```http
GET /api/hierarchy.php?format=tree&include_contact=1
```

用途:

- 外部システムへ代理店一覧を同期する
- 親子関係を取得する
- 代理店別LP URLを取得する

重要フィールド:

| フィールド | 意味 |
|---|---|
| `agency_id` | 外部保存用の代理店ID |
| `code` | `agency_id` と同じ代理店コード |
| `parent_agency_id` | 親代理店の `agency_id` |
| `children` | `format=tree` の場合の下位代理店 |
| `lp_urls` | プロジェクトごとのLP URL |

### 7.3 紹介流入記録

```http
POST /api/referrals/capture
```

用途:

- LPや紹介URL経由で外部システムへ遷移した時
- 登録前の流入を一時記録する時

最小リクエスト:

```json
{
  "system_key": "ai-art-school",
  "referral_token": "rt_example",
  "session_key": "visit_abc123",
  "landing_url": "https://example.com/lp",
  "destination_url": "https://example.com/register"
}
```

### 7.4 紹介確定

```http
POST /api/referrals/confirm
```

用途:

- 登録完了
- 購入完了
- 申込完了
- 成果として代理店に紐づけるタイミング

リクエスト例:

```json
{
  "system_key": "ai-art-school",
  "service_user_id": "local_user_123",
  "referral_session_key": "visit_abc123",
  "order_id": "order_1001",
  "product_code": "ai_art_trial",
  "payment_status": "paid",
  "entitlement_status": "granted",
  "amount": 0,
  "currency": "JPY",
  "assigned_agency_id": "dir260b6d6e",
  "sales_agent_id": "agent001",
  "closing_agent_id": "dir260b6d6e"
}
```

返却される `agency_id` と `canonical_referral_token` を外部システム側でも保存してください。

## 8. 4つの代理店ロール

顧客登録や購入に関わる代理店は、1人とは限りません。以下を分けて扱います。

| フィールド | 意味 |
|---|---|
| `registration_referrer_agency_id` | 最初に登録・流入させた代理店 |
| `assigned_agency_id` | 現在の担当代理店 |
| `sales_agent_id` | 販売・説明した代理店 |
| `closing_agent_id` | クロージングした代理店 |

購入、決済、申込などのイベントでは、可能な限り4項目を明示してください。不明な場合は空欄で送信し、代理店システム側の管理画面で補正します。

## 9. Webhookイベント

代理店システムから外部システムへ送信するイベントは、共通エンベロープを持ちます。

```json
{
  "event_id": "evt_xxxxx",
  "event_type": "agency.updated",
  "event_version": "1.0",
  "source_system_key": "agency-system",
  "target_site_key": "ai-art-school",
  "correlation_id": "corr_xxxxx",
  "occurred_at": "2026-07-21T10:00:00+09:00",
  "data": {}
}
```

外部システム側では、`event_id` または `Idempotency-Key` で重複処理を防止してください。

## 10. SSO

代理店システムにログイン済みの代理店ユーザーが、外部ポータルへ移動するためのSSOです。

流れ:

1. 代理店ユーザーが `sengoku-ai.com` にログイン
2. 外部ポータル連携ボタンをクリック
3. 代理店システムがRS256署名JWTを発行
4. 外部システムのSSO受信URLへリダイレクト
5. 外部システムがJWKSでJWTを検証
6. `sub` / `external_id` の `agent_code` を使ってログイン

JWKS:

```http
GET /api/sso/jwks.php
```

JWT検証条件:

- `alg` は `RS256`
- `iss` は `https://sengoku-ai.com`
- `aud` は連携先ごとの設定値
- `exp` が有効期限内
- `jti` の再利用を拒否

## 11. 連携前の禁止事項

- 外部システム側で `common_user_id` を独自発行しない
- 代理店識別に `agents.id` を使わない
- 未検証メールだけで自動統合しない
- ウォレット残高や権利情報を他システムDBから直接更新しない
- 同じ `Idempotency-Key` で異なる本文を再送しない
- APIキー、JWT秘密鍵、DBパスワードをログやドキュメントに貼らない

## 12. 外部システム側の最低実装

1. `common_user_id` 保存カラム
2. `agency_id` 保存カラム
3. 紹介トークンまたは紹介セッションキー保存カラム
4. API送信用のAI受信用APIキー設定
5. AIから受けるWebhookエンドポイント
6. Webhook受信用APIキー
7. 任意でHMAC署名検証
8. 任意でSSO受信URL

## 13. 本番接続の条件

本番接続は、以下が完了してから開始します。

- テスト環境で共通ID解決が成功している
- 紹介capture/confirmが冪等に成功している
- 代理店階層APIで親子関係を取得できる
- 外部システム受信Webhookの接続テストが成功している
- SSOを使う場合、JWT検証とログインが成功している
- エラー時に再送または手動復旧できる
- APIキーの保管場所とローテーション手順が決まっている

