# 共通ID v2 API

## 目的

ショッピングカート、戦国パスポート、ウォレット、その他外部サービスで先に登録されたユーザーを、代理店システム側の共通IDと紹介関係に紐づけます。

## 認証

外部API連携画面で、連携先サイトごとに発行された「AI側が発行する受信用APIキー」を使います。

```http
x-api-key: {AI側が発行したキー}
```

または:

```http
Authorization: Bearer {AI側が発行したキー}
```

## Feature Flag

管理画面の「共通ID連携」で以下をONにする必要があります。

- `common_id_enabled`
- `external_registration_capture_enabled`
- `referral_v2_enabled` は紹介関係APIのPOST時に必要

## 1. ユーザー紐づけ登録

```http
POST /api/v2/user-mappings
Content-Type: application/json
Idempotency-Key: 任意の一意キー
```

リクエスト例:

```json
{
  "common_user_id": "cu_example_001",
  "service_key": "passport",
  "service_user_id": "passport_user_123",
  "agent_code": "dir260b6d6e",
  "email": "user@example.com",
  "phone": "09000000000",
  "wallet_address": "0x0000000000000000000000000000000000000000",
  "profile": {
    "name": "山田 太郎"
  }
}
```

`common_user_id` が未指定の場合、代理店システム側で自動発行します。

レスポンス例:

```json
{
  "ok": true,
  "mapping": {
    "common_user_id": "cu_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx",
    "service_key": "passport",
    "service_user_id": "passport_user_123",
    "agent_id": 12,
    "status": "active",
    "updated_at": "2026-07-15 10:00:00"
  }
}
```

## 2. ユーザー紐づけ取得

```http
GET /api/v2/user-mappings?service_key=passport&service_user_id=passport_user_123
```

または:

```http
GET /api/v2/user-mappings/by-common-user/{common_user_id}
```

## 3. 紹介関係登録

外部サービスで登録・購入・会員化したユーザーを、紹介元代理店に紐づけます。
プロジェクト別に紹介関係を保持するため、同じユーザーでも商品・サービスごとに別の紹介関係を持てます。

```http
POST /api/v2/referral-relations
Content-Type: application/json
Idempotency-Key: 任意の一意キー
```

リクエスト例:

```json
{
  "common_user_id": "cu_example_001",
  "service_key": "cart",
  "service_user_id": "cart_customer_456",
  "agent_code": "dir260b6d6e",
  "project_slug": "ai-art-school",
  "relation_type": "referral",
  "referral_source": "lp_to_cart",
  "email": "user@example.com"
}
```

`common_user_id` が未指定でも、`service_key` + `service_user_id` の紐づけが既にあれば利用します。紐づけがない場合は新規発行します。

同じ `common_user_id`、`relation_type`、`project_slug/project_id` の紹介関係が既にあり、`locked=1` の場合は、別代理店で上書きしません。

## 4. 紹介関係取得

```http
GET /api/v2/referral-relations/by-common-user/{common_user_id}
```

または:

```http
GET /api/v2/referral-relations?service_key=cart&service_user_id=cart_customer_456
```

## 5. 紹介トークン発行・検証

LP、招待URL、カート遷移、パスポート遷移などで紹介元を失わないためのトークンです。

発行:

```http
POST /api/v2/referral-tokens
Content-Type: application/json
```

```json
{
  "agent_code": "dir260b6d6e",
  "project_slug": "ai-art-school",
  "token_type": "cart",
  "destination_service_key": "cart",
  "destination_url": "https://cart.example.com/register"
}
```

検証:

```http
GET /api/v2/referral-tokens/{token}/validate
```

レスポンスには、紹介元代理店、プロジェクト、遷移先サービス情報が含まれます。

## 6. 紹介セッション記録

外部サービスへ遷移する時点、または外部サービス側の登録開始時点で記録します。

```http
POST /api/v2/referral-sessions
Content-Type: application/json
Idempotency-Key: 任意の一意キー
```

```json
{
  "token": "rt_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx",
  "service_key": "cart",
  "landing_url": "https://sengoku-ai.com/a/dir260b6d6e?project=ai-art-school",
  "destination_url": "https://cart.example.com/register",
  "event_type": "click"
}
```

外部サービス側でユーザー登録完了後、`service_user_id` や `common_user_id` が分かる場合は同じ `session_key` で再送できます。

## 冪等性

POSTでは `Idempotency-Key` を送ることを推奨します。

同じキーで再送された場合、保存済みレスポンスを返します。

## 注意

このAPIは外部サービス起点のユーザー登録・紹介関係取り込み用です。

既存の代理店同期API `/api/integrations/agencies` とは用途が異なります。
