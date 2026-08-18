# 外部連携シンプル化 PR-S02 実装メモ

作成日: 2026-08-03  
対象: `POST /api/integrations/events`

## 1. 実装内容

外部システムから代理店システムへ、注文・決済・権利付与・申込完了などの共通イベントを送信する入口を追加しました。

追加ファイル:

- `api/integrations/events/index.php`

## 2. エンドポイント

```http
POST https://sengoku-ai.com/api/integrations/events
```

認証:

```http
x-api-key: {代理店システムが連携先ごとに発行した受信用APIキー}
```

または:

```http
Authorization: Bearer {代理店システムが連携先ごとに発行した受信用APIキー}
```

スコープ:

```text
events:write
```

連携先の `inbound_scopes` が未設定の場合は、既存互換として許可されます。

## 3. 受信できる主なイベント

```text
user.registered
lead.created
inquiry.created
application.completed
order.created
order.completed
purchase.completed
payment.succeeded
payment.failed
payment.refunded
entitlement.granted
entitlement.revoked
attendance.confirmed
attendance.cancelled
```

イベント名は `order.completed` のようなドット区切り形式を必須にしています。

## 4. リクエスト例

```json
{
  "event": "order.completed",
  "system_key": "sengoku-passport",
  "project_key": "sengoku-influencer",
  "product_code": "passport-standard",
  "occurred_at": "2026-08-03T10:00:00+09:00",
  "idempotency_key": "sengoku-passport:order:12345:completed",
  "common_user_id": "cu_example",
  "service_user_id": "12345",
  "agent_code": "agent_7_8573",
  "referral_session_key": "rs_example",
  "amount": 9800,
  "currency": "JPY",
  "payload": {
    "order_id": "12345",
    "order_item_id": "default"
  }
}
```

## 5. レスポンス例

```json
{
  "ok": true,
  "event": "order.completed",
  "system_key": "sengoku-passport",
  "project_key": "sengoku-influencer",
  "product_code": "passport-standard",
  "common_user_id": "cu_example",
  "agent_code": "agent_7_8573",
  "transaction": {
    "id": 1,
    "common_user_id": "cu_example"
  },
  "warnings": []
}
```

HTTPステータス:

```text
202 Accepted
```

## 6. 重複防止

以下のどちらかを使えます。

```http
Idempotency-Key: sengoku-passport:order:12345:completed
```

またはJSON本文:

```json
{
  "idempotency_key": "sengoku-passport:order:12345:completed"
}
```

同じキー・同じ本文で再送された場合は、保存済みレスポンスを返します。同じキーで本文が違う場合は `IDEMPOTENCY_CONFLICT` を返します。

## 7. 保存される内容

必ず保存されるもの:

- `integration_event_logs`

条件付きで保存されるもの:

- `customer_transactions`

`customer_transactions` に保存されるイベント:

- `order.created`
- `order.completed`
- `purchase.completed`
- `payment.succeeded`
- `payment.failed`
- `payment.refunded`
- `entitlement.granted`
- `entitlement.revoked`
- `application.completed`

保存には、原則として `common_user_id` または `system_key + service_user_id` が必要です。`order_id` がない場合は取引保存せず、受信ログのみ残します。

## 8. 今回あえて実装していないこと

- 報酬再計算
- 確定済み報酬の更新
- 代理店階層の変更
- 外部システムへの再通知
- 管理画面UIの変更

これらは、PR-S03以降で送信先分離・UI整理と合わせて実装します。

## 9. 確認結果

```text
php -l api/integrations/events/index.php
No syntax errors detected
```

