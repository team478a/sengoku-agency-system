# 共通顧客HUB API Stage 2

作成日: 2026-07-20
対象バージョン: 3.6.73

## 目的

外部システムから、代理店システムの `common_user_id` を解決・作成し、各システムのユーザーIDを紐づけ、紹介接点を記録できるようにするための標準APIです。

既存の `/api/v2/*` は互換のため残します。新規連携では本APIを優先します。

## 認証

既存の外部API連携と同じです。

```http
x-api-key: 連携先ごとの受信用APIキー
```

または、

```http
Authorization: Bearer 連携先ごとの受信用APIキー
```

## 共通仕様

- Request/Response は JSON
- 更新系は `Idempotency-Key` 推奨
- feature flag が OFF の場合は `403 FEATURE_DISABLED`
- DBマイグレーション未適用の場合は `503 COMMON_HUB_SCHEMA_NOT_READY`

## 1. 共通顧客の解決

```http
POST /api/common-users/resolve
```

メール、電話、LINE ID、ウォレット、外部システムIDなどから `common_user_id` を取得または作成します。

### Request

```json
{
  "system_key": "shopping",
  "external_user_id": "user_12345",
  "email": "user@example.com",
  "email_verified": true,
  "phone": "09012345678",
  "line_user_id": "Uxxxxxxxx",
  "wallet_address": "0x...",
  "create_if_missing": true,
  "acquisition_channel": "shopping_cart",
  "acquisition_source": "sengoku-rr",
  "campaign_id": "summer-2026"
}
```

### Response

```json
{
  "ok": true,
  "common_user_id": "cu_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx",
  "created": true,
  "matched_by": "created",
  "common_user": {},
  "system_links": [],
  "identities": [],
  "agency_relations": []
}
```

## 2. 共通顧客詳細の取得

```http
GET /api/common-users/{common_user_id}
```

または、

```http
GET /api/common-users?common_user_id=cu_xxx
```

外部システムIDから引く場合:

```http
GET /api/common-users?system_key=shopping&external_user_id=user_12345
```

## 3. システムアカウント紐づけ

```http
POST /api/common-users/{common_user_id}/system-links
```

パスで渡せない環境では以下も利用できます。

```http
POST /api/common-users?action=system-links&common_user_id=cu_xxx
```

### Request

```json
{
  "system_key": "passport",
  "external_user_id": "passport_987",
  "email": "user@example.com",
  "login_email": "login@example.com",
  "phone": "09012345678",
  "display_name": "山田太郎",
  "role_name": "member"
}
```

## 4. 紹介接点の記録

```http
POST /api/referrals/capture
```

紹介URLを踏んだ時点、LPを開いた時点などの接点を記録します。まだ会員登録や購入が完了していない状態です。

### Request

```json
{
  "referral_token": "rt_xxx",
  "referral_session_key": "rs_xxx",
  "system_key": "shopping",
  "external_user_id": "guest_or_user_id",
  "landing_url": "https://example.com/lp",
  "referrer_url": "https://sns.example/post"
}
```

## 5. 紹介者の確定

```http
POST /api/referrals/confirm
```

会員登録、申込、購入などが完了したタイミングで、紹介者を確定します。

### Request

```json
{
  "referral_token": "rt_xxx",
  "referral_session_key": "rs_xxx",
  "system_key": "shopping",
  "external_user_id": "user_12345",
  "email": "user@example.com",
  "relation_type": "referral",
  "referral_source": "order_completed"
}
```

### Response

```json
{
  "ok": true,
  "common_user_id": "cu_xxx",
  "relation": {},
  "touchpoint": {},
  "common_user": {},
  "agency_relations": []
}
```

## Feature Flag

最低限、以下をONにします。

- `common_id_enabled`
- `common_hub_enabled`
- `common_hub_read_enabled`
- `common_hub_write_enabled`
- `referral_v2_enabled`

外部登録・紐づけを使う場合:

- `external_registration_capture_enabled`

システム別に段階運用する場合:

- `passport_integration_enabled`
- `shopping_integration_enabled`
- `wallet_integration_enabled`
- `ai_art_integration_enabled`

## 注意

- `contact_email` と `login_email` は別情報として扱います。
- `common_user_id` は代理店システム側で発行し、外部システムは保存して再利用します。
- 曖昧な一致では自動統合しません。
- 紹介者は原則として後から別の紹介URLで上書きしません。
