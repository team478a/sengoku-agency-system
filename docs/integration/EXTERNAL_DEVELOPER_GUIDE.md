# 千ノ国 代理店システム 外部開発者向け連携ガイド

Version: 3.6.152
Base URL: `https://sengoku-ai.com`

この資料は、戦国パスポート、ショッピングサイト、AIアート教室などの外部サービスが、代理店システムと連携するための実装ガイドです。

## 1. 基本方針

- 代理店システムを「代理店・紹介・共通顧客ID」の中心として扱います。
- 外部サービス側では、ユーザー登録、購入、申込、ログインなどの発生時に代理店システムへ連携します。
- 外部サービスごとにAPIキーを発行します。
- 商品やLPの違いはAPIキーではなく `project_key` で区別します。
- 代理店の外部識別子は `agent_code` を使います。内部DBの `id` は外部連携キーとして使わないでください。

## 2. 用語

| 用語 | 説明 |
| --- | --- |
| `agent_code` | 代理店を外部から識別するコードです。例: `agent_7_8573`, `dir260b6d6e` |
| `project_key` | 商品、案件、LPプロジェクトを識別する値です。プロジェクト管理のスラッグと同じです。例: `sengoku-influencer`, `ai-art-school` |
| `session_key` | 紹介流入を一時的に識別するキーです。`referrals/capture` で発行されます。 |
| `common_user_id` | 複数システムを横断して同じ顧客を識別するIDです。 |
| `site_key` | SSO連携先を識別するキーです。例: `sengoku-passport` |

## 3. 認証

外部サービスから代理店システムへ送信する場合は、次のどちらかで認証します。

```http
x-api-key: {代理店システムが発行したAPIキー}
```

または

```http
Authorization: Bearer {代理店システムが発行したAPIキー}
```

HMAC署名方式は、個別合意がある連携先のみで利用します。通常の外部連携では単純APIキー方式を正式方式とします。

## 4. APIキーの向き

| 種類 | 発行元 | 使う場面 |
| --- | --- | --- |
| 代理店システムが発行するキー | sengoku-ai.com | 外部サービスが sengoku-ai.com のAPIを呼ぶとき |
| 連携先が発行するキー | 外部サービス | sengoku-ai.com から外部サービスへWebhookを送るとき |

1つの外部サービスにつき1つのAPIキーで運用します。外部サービス内で商品が増える場合は、`project_key` で区別してください。

管理画面の「連携ウィザード」では、外部サービスごとのAPIキー発行、送信先URL登録、開発者へ渡すAPI一覧の確認ができます。
管理者はまず「連携ウィザード」で連携先を登録し、次に「プロジェクト管理」で利用する `project_key` を確認してください。

## 5. 主要フロー

### 5.1 紹介URLから登録・購入する場合

1. ユーザーが代理店LPまたは紹介URLから外部サービスへ遷移します。
2. 外部サービスは `POST /api/referrals/capture` を呼びます。
3. 代理店システムは `session_key` を返します。
4. 外部サービスは登録、購入、申込が完了したタイミングで `POST /api/referrals/confirm` を呼びます。
5. 代理店システム側で、顧客、代理店、プロジェクト、成果を紐づけます。

### 5.2 外部サービス側で先にユーザー登録された場合

1. 外部サービスでユーザー登録が完了します。
2. 外部サービスは `POST /api/common-users/resolve` を呼び、共通顧客IDを解決します。
3. 紹介元が分かる場合は `POST /api/referrals/confirm` を呼び、代理店紐づけを確定します。
4. 代理店として登録・更新が必要な場合は `POST /api/integrations/agencies` を呼びます。

### 5.3 代理店システム側から外部サービスへ通知する場合

代理店の承認、登録、更新、停止、削除、問い合わせ発生などは、外部API連携に登録された送信先へWebhookとして送信できます。

## 6. 共通顧客ID解決

正式エンドポイント:

```http
POST /api/common-users/resolve
```

`/api/v2/common-users/resolve` は正式な公開エンドポイントではありません。外部開発者向けには `/api/common-users/resolve` を案内してください。

### リクエスト例

```json
{
  "service_key": "sengoku-passport",
  "external_user_id": "user-1001",
  "email": "user@example.com",
  "display_name": "山田 太郎",
  "wallet_address": "0x0000000000000000000000000000000000000000",
  "metadata": {
    "source": "signup"
  }
}
```

`display_name` を正式名とします。互換のため `name` を受け取る実装にしても構いません。`wallet_address` と `metadata` は任意です。

### レスポンス例

```json
{
  "ok": true,
  "common_user_id": "cu_01JABCDEF123456789",
  "matched": true,
  "agent_code": "agent_7_8573"
}
```

## 7. 紹介流入の記録

```http
POST /api/referrals/capture
```

このAPIは今後も利用可能です。紹介URLから外部サービスへ遷移した時点で呼び出してください。

## 追補: 購入権限・顧客SSO・運用確認

### 購入または申込で権限を付与する場合

外部サービスで購入、年払い、チケット、講座申込などが完了したら、`POST /api/referrals/confirm` または `POST /api/integrations/events` に購入情報を含めて送信してください。

推奨フィールド:

```json
{
  "event_type": "purchase.completed",
  "system_key": "sengoku-passport",
  "common_user_id": "cu_01JABCDEF123456789",
  "external_user_id": "user-1001",
  "agent_code": "agent_7_8573",
  "project_key": "sengoku-influencer",
  "product_code": "annual-plan",
  "order_id": "order-1001",
  "order_item_id": "item-1",
  "amount": 12000,
  "currency": "JPY",
  "entitlement_status": "active",
  "starts_at": "2026-08-18T00:00:00+09:00",
  "expires_at": "2027-08-17T23:59:59+09:00"
}
```

`project_key` は商品・案件・LPプロジェクトの識別子、`product_code` は外部サービス側の商品識別子です。1つの外部サービス内で商品が増えても、APIキーを増やさず `project_key` と `product_code` で区別します。

### 顧客SSOトークン

外部サービスが共通顧客を代理店システム基準でログインさせたい場合は、顧客SSOトークンを発行できます。

```http
POST /api/sso/customer-token
```

認証は通常の外部連携APIキーです。レスポンスのJWTは既存SSOと同じくRS256で署名され、JWKSで検証できます。

### 管理画面での確認場所

| 確認したいこと | 管理画面 |
| --- | --- |
| 顧客ごとの購入権限 | 共通顧客HUB > 購入権限 |
| 外部サービスごとの接続設定 | 外部連携 > 連携先管理 |
| 基本接続・購入・権限・顧客SSOのテスト | 外部連携 > 連携先管理 > 接続テスト |
| 送受信ログ・失敗ログ・再送 | 運用・設定 > 外部連携ログ |

### 接続テスト

連携先管理では、次の4種類をテストできます。

- 基本: 代理店同期APIの受信確認
- 購入: `purchase.completed` の受信確認
- 権限: `entitlement.granted` の受信確認
- 顧客SSO: `customer_sso.token_issued` の受信確認

テスト送信には `dry_run: true` が入ります。外部サービス側は保存せず、受信できたら200系レスポンスを返してください。

### リクエスト例

```json
{
  "agent_code": "agent_7_8573",
  "project_key": "sengoku-influencer",
  "landing_url": "https://sengoku-ai.com/a/agent_7_8573?project=sengoku-influencer",
  "service_key": "sengoku-passport"
}
```

### レスポンス例

```json
{
  "ok": true,
  "session_key": "ref_01JABCDEF123456789",
  "agent_code": "agent_7_8573",
  "project_key": "sengoku-influencer"
}
```

## 8. 登録・購入・申込の確定

```http
POST /api/referrals/confirm
```

`project_key` は原則送信してください。未送信の場合は、既定プロジェクトに紐づく、またはプロジェクト未指定として記録される可能性があります。外部サービス側では必ず明示する運用を推奨します。

### リクエスト例

```json
{
  "session_key": "ref_01JABCDEF123456789",
  "agent_code": "agent_7_8573",
  "project_key": "sengoku-influencer",
  "service_key": "sengoku-passport",
  "external_user_id": "user-1001",
  "event_type": "purchase_completed",
  "amount": 9800,
  "currency": "JPY",
  "product_code": "passport-basic"
}
```

## 9. 外部イベント受信

```http
POST /api/integrations/events
```

外部サービスで発生した購入、登録、会員状態変更などを代理店システムへ通知するためのAPIです。

### リクエスト例

```json
{
  "event_type": "purchase.completed",
  "service_key": "sengoku-passport",
  "project_key": "sengoku-influencer",
  "external_user_id": "user-1001",
  "agent_code": "agent_7_8573",
  "product_code": "passport-basic",
  "amount": 9800,
  "occurred_at": "2026-08-03T10:00:00+09:00"
}
```

## 10. 代理店同期

```http
POST /api/integrations/agencies
```

外部サービス側で登録されたユーザーを、代理店システムの代理店・ディレクター・アドバイザーとして登録または更新する場合に使います。

### リクエスト例

```json
{
  "external_id": "passport-user-1001",
  "agent_code": "agent_7_8573",
  "name": "株式会社サンプル",
  "manager_name": "山田 太郎",
  "position": "agent",
  "parent_agent_code": null,
  "contact_email": "contact@example.com",
  "login_email": "login@example.com",
  "phone": "09000000000",
  "status": "active"
}
```

`contact_email` と `login_email` は別項目です。両方指定された場合も、互いに上書きしません。

## 11. 階層取得

```http
GET /api/hierarchy.php?format=tree&include_contact=1&include_sso=1
```

### 主なパラメータ

| パラメータ | 説明 |
| --- | --- |
| `format` | `tree` または `flat` |
| `root_code` | 指定した代理店配下のみ取得 |
| `include_contact` | `1` の場合、連絡先情報を含める |
| `include_sso` | `1` の場合、SSO起動URLを含める |

### レスポンス例

```json
{
  "ok": true,
  "format": "tree",
  "items": [
    {
      "agent_code": "agent_7_8573",
      "code": "agent_7_8573",
      "name": "ストックビジネス合同会社",
      "manager_name": "山本",
      "position": "agent",
      "role_level": 3,
      "parent_agent_code": null,
      "contact_email": "contact@example.com",
      "phone": "09000000000",
      "line_url": "https://lin.ee/example",
      "projects": [
        {
          "project_key": "sengoku-influencer",
          "project_slug": "sengoku-influencer",
          "lp_url": "https://sengoku-ai.com/a/agent_7_8573?project=sengoku-influencer"
        }
      ],
      "children": [
        {
          "agent_code": "dir260b6d6e",
          "code": "dir260b6d6e",
          "name": "yamagama",
          "manager_name": "yamada",
          "position": "director",
          "role_level": 2,
          "parent_agent_code": "agent_7_8573",
          "contact_email": "director@example.com",
          "children": []
        }
      ]
    }
  ]
}
```

互換性のため `code` と `agent_code` の両方を返す場合があります。外部サービス側では `agent_code` を優先してください。

## 12. Webhook受信

代理店システムから外部サービスへ、以下のイベントを送信できます。

| イベント | 用途 |
| --- | --- |
| `connection_test` | 接続テスト |
| `agency.created` | 代理店登録 |
| `agency.updated` | 代理店更新 |
| `agency.suspended` | 代理店停止 |
| `agency.deleted` | 代理店削除 |
| `lead_created` | 問い合わせ発生 |
| `common_user.merged` | 共通顧客ID統合 |
| `common_user.assigned_agent.updated` | 顧客の担当代理店変更 |

Webhook受信側は、成功時にHTTP 200を返してください。

## 13. SSO

SSO起動URL:

```http
GET /agent/sso_launch.php?client={site_key}
```

JWKS:

```http
GET /api/sso/jwks.php
```

JWTの検証方式:

- 署名方式: RS256
- 公開鍵取得: JWKS
- 必須検証: `iss`, `aud`, `exp`, `jti`
- `aud`: 連携先の `site_key`
- `jti`: リプレイ防止に利用

## 14. 冪等性

POST APIでは、可能な限り `Idempotency-Key` を送信してください。同じイベントの再送では同じ値を使います。

```http
Idempotency-Key: purchase-user-1001-20260803-001
```

## 15. エラーレスポンス

```json
{
  "ok": false,
  "error": {
    "code": "invalid_api_key",
    "message": "API key is invalid."
  }
}
```

`error.code` はログ表示だけでなく、再送可否や設定ミスの判定に使えます。

## 16. よくある確認事項

### `/api/v2/common-users/resolve` は使いますか

使いません。正式な公開パスは `/api/common-users/resolve` です。

### `project_key` と `project_slug` は違いますか

値は同じです。正式フィールド名は `project_key` です。既存互換のため `project_slug` を受け取れる設計にしても構いません。

### `referrals/capture` は必要ですか

紹介流入を正確に記録する場合は必要です。廃止予定はありません。

### 商品が増えるたびにAPIキーを増やしますか

増やしません。同じ外部サービスならAPIキーは1つで、商品や案件は `project_key` と `product_code` で分けます。
