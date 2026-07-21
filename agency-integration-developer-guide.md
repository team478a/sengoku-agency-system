# 代理店システム連携 開発者向け説明書

Version: 3.6.40  
作成日: 2026-07-08  
対象システム:

- 中心システム: `sengoku-ai.com` 代理店システム
- 連携先システム例: `sengoku-rr.com`
- 今後追加される外部システム、商品システム、会員ポータル

## 1. この連携で実現したいこと

`sengoku-ai.com` の代理店システムを中心に、他システムでも同じ代理店階層を使えるようにします。

たとえば以下のような使い方です。

- 代理店システムで登録・承認されたユーザーが、`sengoku-rr.com` にもログインできる
- 代理店システムの階層、つまり「誰の配下か」を `sengoku-rr.com` 側にも引き継ぐ
- `sengoku-rr.com` 側で新規登録されたユーザーを、代理店システムにも登録する
- 将来、別の商品・別サービスが増えても、代理店情報を同じ形式で連携する

## 2. 重要な前提

双方向連携では、APIキーは2種類必要です。

| APIキー | 発行するシステム | 登録する場所 | 目的 |
|---|---|---|---|
| `sengoku-ai.com 受信用APIキー` | sengoku-ai.com | sengoku-rr.com側 | RRからAIへ送信する時に使う |
| `sengoku-rr.com 受信用APIキー` | sengoku-rr.com | sengoku-ai.com側 | AIからRRへ送信する時に使う |

つまり、`sengoku-rr.com 受信用APIキー（AIからRRへ送信）` は、`sengoku-rr.com` 側で実装・発行してもらう必要があります。

このキーがRR側に未実装の場合、`sengoku-ai.com → sengoku-rr.com` の送信はできません。

## 3. 実装責任の分担

### sengoku-ai.com 側で実装済みの役割

`sengoku-ai.com` 側では、以下を担当します。

- 代理店・ディレクター・アドバイザーなどの階層管理
- 外部から代理店データを受け取るAPI
- 外部から階層データを取得するAPI
- AI側で承認・登録・更新された代理店情報を外部へPOSTする処理
- 管理画面で外部連携先URL・外部APIキーを設定する画面

### sengoku-rr.com 側で実装が必要な役割

`sengoku-rr.com` 側では、以下の実装が必要です。

- RR側の受信用APIキーを発行・表示・再発行する管理画面
- AI側からのPOSTを受けるAPI
- 受け取ったAPIキーの認証
- `external_id` をキーにした代理店の登録・更新
- `parent_external_id` による親子階層の保存
- 必要であれば、受信した代理店をRR側ポータルへログイン可能にする処理

## 4. 通信方向

### 方向A: sengoku-rr.com から sengoku-ai.com へ送信

RR側でユーザー登録・代理店登録・更新が発生した場合、RRからAIへ送ります。

```http
POST https://sengoku-ai.com/api/integrations/agencies
```

使うキー:

```http
x-api-key: {sengoku-ai.com 受信用APIキー}
```

### 方向B: sengoku-ai.com から sengoku-rr.com へ送信

AI側で代理店承認・登録・権限変更・状態変更が発生した場合、AIからRRへ送ります。

```http
POST https://sengoku-rr.com/api/integrations/agencies
```

使うキー:

```http
x-api-key: {sengoku-rr.com 受信用APIキー}
```

この受信用APIキーはRR側で発行し、AI側の管理画面へ登録します。

AI側管理画面の「送信先URL」は、以下のどちらでも登録できます。

- `https://sengoku-rr.com`
- `https://sengoku-rr.com/api/integrations/agencies`

ドメインだけを登録した場合、AI側が `/api/integrations/agencies` を自動で付与して送信します。
運用上は `https://sengoku-rr.com` のようにドメインだけを登録する形を推奨します。

AI側の管理画面には「接続テスト」ボタンがあります。
このボタンはRR側APIへ以下のようなテストJSONをPOSTします。
RR側は `event=connection_test` または `dry_run=true` の場合、代理店データとして保存せず、認証と受信可否だけを確認して `200` 系レスポンスを返してください。

```json
{
  "event": "connection_test",
  "dry_run": true,
  "source": "sengoku-ai",
  "external_id": "__connection_test__"
}
```

## 5. sengoku-rr.com 側に必要なAPI

RR側には、最低限以下のAPIを実装してください。

```http
POST /api/integrations/agencies
```

役割:

- AI側から送られてきた代理店情報を受け取る
- APIキーを検証する
- `external_id` をキーに代理店を新規登録または更新する
- `parent_external_id` をもとに親子階層を保存する
- 正常時はJSONで成功レスポンスを返す

## 6. 送信JSONの形式

```json
{
  "event": "approved",
  "source": "sengoku-ai",
  "source_agent_id": 123,
  "external_id": "dir260b6d6e",
  "parent_external_id": "agent_7_8573",
  "name": "山田代理店",
  "contact_name": "山田太郎",
  "contact_email": "contact@example.com",
  "login_email": "login@example.com",
  "phone": "08000000000",
  "status": "active",
  "role_level": 2,
  "role_label": "ディレクター",
  "lp_urls": [
    {
      "project_slug": "sengoku-influencer",
      "project_name": "戦国インフルエンサー",
      "url": "https://sengoku-ai.com/a/dir260b6d6e?project=sengoku-influencer"
    }
  ],
  "sso_urls": [
    {
      "client_key": "sengoku-rr",
      "client_name": "sengoku-rr.com",
      "audience": "sengoku-rr",
      "url": "https://sengoku-ai.com/agent/sso_launch.php?client=sengoku-rr"
    }
  ],
  "updated_at": "2026-07-08T09:30:00+09:00"
}
```

`sso_urls` は、SSO連携設定で有効になっている外部サイトへのログイン起動URLです。
代理店データの一意キーは `external_id`、SSOのJWT内の一意キーも `external_id` です。RR側ではこの値で同じユーザーとして紐付けてください。

## 7. RR側の実装チェックリスト

- `POST /api/integrations/agencies` を作る
- `x-api-key` と `Authorization: Bearer` のどちらでも認証できるようにする
- RR側で受信用APIキーを発行できるようにする
- `external_id` を一意キーとして保存する
- 同じ `external_id` が来たら更新する
- `parent_external_id` を保存する
- 親が未登録でもエラーにせず保存する
- `contact_email` と `login_email` を別々に扱う
- 正常時に `success: true` のJSONを返す

## 8. 開発者への結論

1. `sengoku-ai.com 受信用APIキー` はAI側にあります。
2. `sengoku-rr.com 受信用APIキー` はRR側で実装・発行が必要です。
3. AI側はRR側の `POST /api/integrations/agencies` に代理店情報を送ります。
4. RR側は `external_id` で登録・更新し、`parent_external_id` で階層を保存してください。
5. `contact_email` と `login_email` は別項目として扱ってください。
