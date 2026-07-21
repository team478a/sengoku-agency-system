# 現行DBスキーマ整理

## 主要テーブル

### agents

代理店・エージェント・ディレクター・アドバイザーなどを管理する中核テーブルです。

主なカラム:

| カラム | 用途 |
|---|---|
| `id` | 内部ID |
| `external_id` | 既存代理店同期API用ID |
| `agent_code` | LP URL・公開識別子 |
| `level` | 権限階層 |
| `parent_id` | 親代理店 |
| `agent_name` | 代理店名 |
| `person_name` | 担当者名 |
| `email` | 連絡先メール |
| `login_email` | ログイン用メール |
| `position_type` | アドバイザー種別 |
| `position_label` | 表示名称 |
| `status` | active / inactive |

### leads

LPからの問い合わせを保存します。

現状は `agent_id` に紐づきます。将来的には `common_user_id` や `referral_token_id` を追加することで、外部サービス起点の登録や購入と関連付けられます。

### access_logs

LPのPV、LINEクリック、問い合わせクリックを保存します。

### recruitment_links

下位代理店・アドバイザー招待URLを管理します。

### external_partner_sites

外部連携先ごとのAPI設定を管理します。

| カラム | 用途 |
|---|---|
| `site_key` | 連携先識別子 |
| `name` | 連携先名 |
| `base_url` | 送信先URL |
| `api_key` | 連携先が発行したキー |
| `inbound_api_key` | 代理店システムが発行したキー |
| `status` | active / inactive |

### sso_clients

SSO連携先を管理します。

### login_logs

管理者・代理店ログイン履歴を保存します。

## 追加が必要なテーブル

### service_user_mappings

各サービスのユーザーIDと共通IDを紐づけます。

想定カラム:

| カラム | 用途 |
|---|---|
| `id` | 内部ID |
| `common_user_id` | 全サービス共通の人物ID |
| `service_key` | `agency`, `cart`, `passport`, `wallet` など |
| `service_user_id` | 各サービス側のユーザーID |
| `email_hash` | 任意。照合補助用 |
| `phone_hash` | 任意。照合補助用 |
| `wallet_address` | 任意 |
| `status` | active / merged / disabled |
| `created_at` | 作成日時 |
| `updated_at` | 更新日時 |

制約:

- `common_user_id + service_key + service_user_id` は一意
- `service_key + service_user_id` は一意

### agency_customer_relations

顧客・会員がどの代理店に紐づくかを保存します。

想定カラム:

| カラム | 用途 |
|---|---|
| `id` | 内部ID |
| `common_user_id` | 共通ユーザーID |
| `agent_id` | 紐づく代理店 |
| `project_id` | 対象プロジェクト。未指定時は `0` |
| `source_service_key` | 登録元サービス |
| `source_service_user_id` | 登録元サービスのユーザーID |
| `referral_token_id` | 紹介トークン |
| `relation_type` | referral / purchaser / member など |
| `status` | active / inactive |
| `created_at` | 作成日時 |

制約:

- `common_user_id + relation_type + project_id` は一意
- 同じユーザーでも、プロジェクトが違えば別の紹介関係を保持できます

### referral_tokens

代理店・プロジェクト・用途別の紹介トークンを管理します。

主な用途:

- LPから外部サービスへ遷移するときの紹介元保持
- カート、パスポート、将来追加サービスでの紹介元検証
- 代理店・プロジェクト・遷移先サービス単位のトークン管理

### referral_sessions

LPや外部サービス遷移時のクリック・セッションを記録します。

主な用途:

- 紹介トークン経由のクリック記録
- 外部サービス側の仮登録・登録完了イベントとの紐づけ
- `common_user_id` が後から判明した場合の追記

### integration_idempotency_keys

外部APIの二重送信を防ぎます。

### integration_event_logs

外部連携の受信・送信・エラー履歴を保存します。

### sale_events

将来の売上連携用です。初期フェーズでは作成のみ、または後続フェーズで追加します。

## 追加方針

既存テーブルの意味を変えず、追加テーブル中心で進めます。

- `agents.external_id` を `common_user_id` に流用しない
- `leads.agent_id` の既存挙動は維持する
- `recruitment_links` は代理店招待用として維持する
- 顧客紹介・購入者紹介は新しい `referral_tokens` / `agency_customer_relations` で扱う
