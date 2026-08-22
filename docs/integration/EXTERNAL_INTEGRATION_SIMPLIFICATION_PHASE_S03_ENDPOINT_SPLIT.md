# External Integration Simplification Phase S03

作成日: 2026-08-03

## 目的

外部連携の送信先を、用途ごとに分離しました。

これまで `base_url` から自動生成される `/api/integrations/agencies` に、代理店マスタ同期と共通イベントが混在しやすい状態でした。

Phase S03 では、既存互換を残したまま以下の2系統に分けます。

| 用途 | 送信先 |
| --- | --- |
| 代理店マスタ同期 | `agency_sync_endpoint` |
| 共通イベント通知 | `common_event_endpoint` |

## DB変更

`external_partner_sites` に以下を追加します。

| カラム | 用途 |
| --- | --- |
| `agency_sync_endpoint` | 外部サービス側の代理店マスタ受信用URL |
| `common_event_endpoint` | 外部サービス側の共通イベント受信用URL |

既存の `base_url` は互換用に残します。

マイグレーション適用時、既存データは以下のように補完されます。

| `base_url` の値 | `agency_sync_endpoint` | `common_event_endpoint` |
| --- | --- | --- |
| `https://example.com` | `https://example.com/api/integrations/agencies` | `https://example.com/api/integrations/events` |
| `https://example.com/api/integrations/agencies` | 同じ値 | `https://example.com/api/integrations/events` |
| `https://example.com/api/integrations/events` | `https://example.com/api/integrations/agencies` | 同じ値 |

## 送信先の選択

`dispatchExternalPartnerEvent()` はイベント種別で送信先を選びます。

### `agency_sync_endpoint` に送るイベント

- `connection_test`
- `upsert`
- `agency.*`
- `agent.*`
- `created`
- `updated`
- `deleted`
- `suspended`

### `common_event_endpoint` に送るイベント

上記以外のイベントです。

例:

- `lead_created`
- `common_user.updated`
- `common_user.merged`
- `common_user.assigned_agent.updated`
- `order.created`
- `order.completed`
- `purchase.completed`
- `payment.succeeded`
- `entitlement.granted`

## 互換性

既存の `base_url` と `api_key` はそのまま使えます。

新カラムが空の場合は、`base_url` から自動で送信先を作ります。

そのため、既存連携先は即時に設定変更しなくても動作します。

## 注意

外部サービス側で `/api/integrations/events` をまだ実装していない場合、共通イベント通知は失敗ログとして残ります。

その場合は、外部サービス側に共通イベント受信用APIを追加するか、一時的に `common_event_endpoint` を既存の受信可能URLへ設定してください。
