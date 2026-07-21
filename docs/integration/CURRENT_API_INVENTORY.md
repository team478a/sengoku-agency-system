# 現行API棚卸し

## 既存API

### 代理店階層取得API

```text
GET /api/hierarchy.php
```

用途:

- 代理店一覧取得
- 階層取得
- プロジェクト別LP URL取得
- 連絡先情報の任意取得
- SSO起動URLの任意取得

主なパラメータ:

| パラメータ | 内容 |
|---|---|
| `format=tree` | 階層を `children` 配列で返す |
| `format=flat` | フラットな一覧で返す |
| `root_code` | 指定代理店配下だけ取得 |
| `include_contact=1` | メール・電話・LINE URLを含める |
| `include_sso=1` | SSO起動URLを含める |

認証:

- `Authorization: Bearer {APIキー}`
- `x-api-key: {APIキー}`

現在は `system_settings.external_api_token` または `external_partner_sites.inbound_api_key` を受け付けます。

### 代理店同期API

```text
POST /api/integrations/agencies
GET  /api/integrations/agencies
GET  /api/integrations/agencies/{external_id}
```

用途:

- 外部システムから代理店を登録・更新
- 外部システムへ代理店情報を同期
- `external_id` ベースの代理店参照

主な項目:

| 項目 | 内容 |
|---|---|
| `external_id` | 外部システム側の代理店ID |
| `parent_external_id` | 親代理店の外部ID |
| `name` | 代理店名 |
| `contact_name` | 担当者名 |
| `contact_email` | 連絡先メール |
| `login_email` | ログイン用メール |
| `status` | active / inactive |

注意:

このAPIは代理店同期用であり、顧客・購入者・会員の共通ID連携用ではありません。

### SSO連携

```text
GET /agent/sso_launch.php?client={client_key}
GET /api/sso/jwks.php
```

用途:

- 代理店システムにログイン済みの代理店ユーザーを外部ポータルへ遷移させる
- JWT署名鍵をJWKSで公開する

現行JWTでは `sub` と `external_id` に `agent_code` を入れています。

## 現行APIで不足しているもの

添付仕様に対して、以下のAPIは未実装です。

### 共通IDマッピングAPI

必要な用途:

- 外部サービス側のユーザーIDを `common_user_id` に紐づける
- 既存ユーザーが別サービスに登録された時に同一人物として扱う

想定:

```text
POST /api/v2/user-mappings
GET  /api/v2/user-mappings/by-common-user/{common_user_id}
GET  /api/v2/user-mappings/by-service-user
```

### 紹介関係API

必要な用途:

- ショッピングカートやパスポート側で登録されたユーザーを代理店紹介関係へ戻す
- どの代理店・どの紹介トークン・どのサービスから来たかを保存する

想定:

```text
POST /api/v2/referral-relations
GET  /api/v2/referral-relations/by-common-user/{common_user_id}
```

### 紹介トークン検証API

実装済み:

```text
GET /api/v2/referral-tokens/{token}/validate
POST /api/v2/referral-tokens
POST /api/v2/referral-sessions
GET  /api/v2/referral-sessions?session_key={session_key}
```

用途:

- LP、招待URL、カート遷移、外部サービス登録時に紹介元を検証する
- 外部サービスへ遷移したセッションを記録する
- 登録完了後に `service_user_id` / `common_user_id` を同じセッションへ戻す

### 招待受諾API

必要な用途:

- 外部サービス側の登録完了を代理店システムへ返す

想定:

```text
POST /api/v2/agent-invitations/accept
```

## 認証方針

既存の外部APIキー管理を活かし、連携先ごとに以下を分けます。

| キー | 発行元 | 用途 |
|---|---|---|
| AI発行キー | 代理店システム | 外部サービスから代理店システムへ送信 |
| 連携先発行キー | 外部サービス | 代理店システムから外部サービスへ送信 |

新しい v2 API も、まずは既存の `external_partner_sites.inbound_api_key` を利用できます。
