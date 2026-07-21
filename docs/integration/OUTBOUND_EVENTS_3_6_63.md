# 外部連携イベント送信 v3.6.63

## 目的

代理店システムをハブとして、登録済みの外部システムへ代理店・問い合わせ・紹介関係の変更を通知します。

対象は管理画面の「外部API連携」に登録されている有効な連携先です。

## 認証

代理店システムから外部サイトへ送信する場合は、外部サイト側が発行したAPIキーを使用します。

送信ヘッダー:

```http
Content-Type: application/json
Accept: application/json
x-api-key: {連携先サイトが発行したAPIキー}
Authorization: Bearer {連携先サイトが発行したAPIキー}
```

## 送信先

管理画面でドメインのみを登録した場合、自動で以下に送信します。

```text
https://example.com/api/integrations/agencies
```

独自エンドポイントを使う場合は、管理画面にエンドポイントまで入力してください。

## 送信されるイベント

### 代理店系

既存の代理店登録・更新・権限変更・削除処理から送信されます。

代表例:

```text
admin_created
admin_updated
role_updated
created_by_parent
updated_by_parent
deleted
```

ペイロードは既存の代理店同期形式です。

```json
{
  "event": "admin_updated",
  "source": "sengoku-ai",
  "source_agent_id": 12,
  "external_id": "dir260b6d6e",
  "parent_external_id": "agent001",
  "name": "代理店名",
  "contact_name": "担当者名",
  "contact_email": "contact@example.com",
  "login_email": "contact@example.com",
  "phone": "09000000000",
  "status": "active",
  "role_level": 2,
  "role_label": "ディレクター",
  "lp_urls": [],
  "sso_urls": [],
  "updated_at": "2026-07-15T11:40:00+09:00"
}
```

### 問い合わせ系

LP問い合わせが保存された時に `lead_created` を送信します。

```json
{
  "event": "lead_created",
  "source": "sengoku-ai",
  "lead_id": 100,
  "common_user_id": "cu_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx",
  "referral_token_id": 1,
  "referral_session_key": "rs_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx",
  "referral_source": "lp_contact",
  "source_agent_id": 12,
  "external_id": "dir260b6d6e",
  "agent_name": "代理店名",
  "contact_name": "担当者名",
  "project_id": 2,
  "project_slug": "ai-art-school",
  "project_name": "AIアート教室",
  "template_id": 7,
  "customer": {
    "name": "問い合わせ者名",
    "email": "user@example.com",
    "phone": "09000000000",
    "message": "問い合わせ内容"
  },
  "source_url": "https://sengoku-ai.com/a/dir260b6d6e?project=ai-art-school",
  "status": "new",
  "created_at": "2026-07-15T11:40:00+09:00",
  "updated_at": "2026-07-15T11:40:00+09:00"
}
```

## 送信結果ログ

各連携先への送信結果は `integration_event_logs` に保存されます。

保存される主な項目:

```text
direction = outbound
site_key
event_type
endpoint
http_status
success
common_user_id
agent_id
request_body
response_body
error_message
```

これにより、どの外部サイトへの同期が成功・失敗したかを後から確認できます。
