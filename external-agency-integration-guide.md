# 代理店連携API 仕様書

Version: 3.6.40  
対象: sengoku-ai.com 代理店システム / sengoku-rr.com / 今後追加される外部システム

## 1. 目的

`sengoku-ai.com` の代理店システムを中心に、他のシステムでも同じ代理店情報・階層情報を使えるようにします。

## 2. 双方向連携の全体像

| 方向 | 内容 | 送信元 | 受信先 |
|---|---|---|---|
| A | RRで登録・更新した代理店をAIへ送る | sengoku-rr.com | sengoku-ai.com |
| B | AIで承認・登録・更新した代理店をRRへ送る | sengoku-ai.com | sengoku-rr.com |

どちらの方向でも、受信側は以下のAPIを用意します。

```http
POST /api/integrations/agencies
```

## 3. APIキーは接続先ごとに2つ必要

| APIキー | 発行元 | 登録先 | 使う場面 |
|---|---|---|---|
| AI側が発行する受信用APIキー | sengoku-ai.com | 対象の外部サイト | 外部サイトからAIへ送信 |
| 外部サイトが発行する受信用APIキー | 外部サイト | sengoku-ai.com | AIから外部サイトへ送信 |

AI側が発行するキーは、管理画面の `外部API連携` で連携先サイトを追加すると、接続先ごとに発行されます。
外部サイト側が発行するキーは、外部サイト側で発行・検証する実装が必要です。

## 4. RRからAIへ送信する場合

```http
POST https://sengoku-ai.com/api/integrations/agencies
x-api-key: {その連携先専用のAI発行キー}
```

## 5. AIからRRへ送信する場合

```http
POST https://sengoku-rr.com/api/integrations/agencies
x-api-key: {sengoku-rr.com 受信用APIキー}
```

AI側管理画面の「送信先URL」は、以下のどちらでも登録できます。

- `https://sengoku-rr.com`
- `https://sengoku-rr.com/api/integrations/agencies`

ドメインだけを登録した場合、AI側が `/api/integrations/agencies` を自動で付与します。
通常は `https://sengoku-rr.com` のようにドメインだけを登録してください。

AI側の管理画面には「接続テスト」ボタンがあります。
RR側は `event=connection_test` または `dry_run=true` のリクエストを受けた場合、代理店データとして保存せず、認証と受信可否だけを確認して `200` 系レスポンスを返してください。

```json
{
  "event": "connection_test",
  "dry_run": true,
  "source": "sengoku-ai",
  "external_id": "__connection_test__"
}
```

RR側で必要な実装:

- `POST /api/integrations/agencies`
- RR側受信用APIキーの発行
- APIキー認証
- `external_id` による登録・更新
- `parent_external_id` による階層保存

## 6. AI側から送るJSON例

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
代理店データとSSOは、どちらも `external_id` を共通キーとして紐付けます。

## 7. IDと階層

AIからRRへ送る場合:

```text
external_id = agent_code
parent_external_id = 上位代理店の agent_code
```

RR側では、`external_id` を一意キーとして保存してください。  
同じ `external_id` が届いた場合は新規作成ではなく更新です。

## 8. メール項目

`contact_email` と `login_email` は別項目です。

- `contact_email`: 連絡・通知用メール
- `login_email`: ポータルログイン用メール

両方指定された場合も、同じ値で上書きせず、独立して保存してください。

## 9. 正常レスポンス

```json
{
  "success": true,
  "data": {
    "external_id": "dir260b6d6e",
    "status": "active",
    "synced": true
  }
}
```

## 10. RR側の実装チェックリスト

- RR側受信用APIキーを発行できる
- `POST /api/integrations/agencies` を実装する
- `x-api-key` または `Authorization: Bearer` で認証する
- `external_id` を一意キーとして保存する
- 同じ `external_id` が届いたら更新する
- `parent_external_id` を保存する
- 親が未登録でもエラーにせず保存する
- `contact_email` と `login_email` を別項目として保存する
- 成功時に `success: true` を返す

## 11. 重要な結論

1. `sengoku-ai.com 受信用APIキー` はAI側にあります。
2. `sengoku-rr.com 受信用APIキー` はRR側で実装・発行が必要です。
3. AI側はRR側の `POST /api/integrations/agencies` に代理店情報を送ります。
4. RR側は `external_id` で登録・更新し、`parent_external_id` で階層を保存します。
