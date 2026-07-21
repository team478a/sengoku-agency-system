# 外部連携API 開発者向け仕様書

Version: 3.6.70  
対象: sengoku-ai.com 代理店システム / sengoku-rr.com / 今後追加される外部サイト

## 1. 目的

`sengoku-ai.com` の代理店システムを中心に、外部サイトでも同じ代理店情報、親子階層、共通ID、SSOログインを利用できるようにするための仕様です。

この仕様は `sengoku-rr.com` だけでなく、今後追加されるショッピングカート、会員ポータル、商品別サイトにも同じ考え方で適用します。

## 2. 連携方向

外部連携は双方向です。

| 方向 | 送信元 | 送信先 | 主な用途 |
|---|---|---|---|
| 外部サイト -> 代理店システム | sengoku-rr.com 等 | sengoku-ai.com | 外部サイトで登録・更新されたユーザーを代理店システムへ登録する |
| 代理店システム -> 外部サイト | sengoku-ai.com | sengoku-rr.com 等 | 代理店システムで承認・登録・更新された代理店情報を外部サイトへ同期する |

## 3. APIキーの考え方

APIキーは接続先ごとに2種類あります。1つのキーを全サイトで使い回す運用ではありません。

| キー | 発行元 | 登録先 | 使う場面 |
|---|---|---|---|
| AI側が発行する受信用APIキー | sengoku-ai.com | 外部サイト側 | 外部サイトから sengoku-ai.com へPOSTするとき |
| 外部サイト側が発行する受信用APIキー | 外部サイト | sengoku-ai.com 管理画面 | sengoku-ai.com から外部サイトへPOSTするとき |

例: `sengoku-rr.com` と `戦国パスポート` を連携する場合、それぞれ別のAI側発行キー、別の外部サイト側発行キーを使います。

## 4. 外部サイトからAIへ送信するAPI

外部サイトでユーザー登録・代理店登録・更新が発生した場合、外部サイトからAI側へPOSTします。

```http
POST https://sengoku-ai.com/api/integrations/agencies
Content-Type: application/json
x-api-key: {AI側が発行した、その外部サイト専用の受信用APIキー}
```

`Authorization: Bearer {APIキー}` でも認証可能にしてあります。

## 5. AIから外部サイトへ送信するAPI

AI側で代理店申請の承認、代理店登録、権限変更、停止、削除などが発生した場合、AI側から登録済みの外部サイトへPOSTします。

```http
POST https://example.com/api/integrations/agencies
Content-Type: application/json
x-api-key: {外部サイト側が発行した受信用APIキー}
Authorization: Bearer {外部サイト側が発行した受信用APIキー}
```

AI側管理画面の送信先URLは、以下のどちらでも登録できます。

```text
https://example.com
https://example.com/api/integrations/agencies
```

ドメインだけを登録した場合、AI側が `/api/integrations/agencies` を自動付与します。

## 6. 外部サイト側に必要な受信API

外部サイト側では、最低限以下のAPIを実装してください。

```http
POST /api/integrations/agencies
```

必要な処理:

- `x-api-key` または `Authorization: Bearer` でAPIキーを検証する
- `external_id` を一意キーとして代理店ユーザーを登録または更新する
- `parent_external_id` を使って親子階層を保存する
- `contact_email` と `login_email` を別項目として保存する
- `event=connection_test` または `dry_run=true` の場合は本登録せず、認証と受信可否だけ確認して `2xx` を返す
- 正常時はJSONで成功レスポンスを返す

## 7. 接続テスト

AI側管理画面の「外部API連携」から、連携先ごとに接続テストを実行できます。

テスト時に送信されるJSON例:

```json
{
  "event": "connection_test",
  "dry_run": true,
  "source": "sengoku-ai",
  "external_id": "__connection_test__"
}
```

外部サイト側は、このリクエストを本番データとして保存しないでください。APIキー認証、JSON受信、レスポンス返却だけを確認します。

推奨レスポンス:

```json
{
  "success": true,
  "message": "connection ok"
}
```

## 8. AIから送信される代理店JSON例

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
    },
    {
      "project_slug": "ai-art-school",
      "project_name": "AIアート教室",
      "url": "https://sengoku-ai.com/a/dir260b6d6e?project=ai-art-school"
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
  "updated_at": "2026-07-15T15:00:00+09:00"
}
```

## 9. 一意キーと階層

AI側から送る代理店の一意キーは `external_id` です。現在は代理店コード、つまり `agent_code` を使います。

```text
external_id = agent_code
parent_external_id = 親代理店の agent_code
```

外部サイト側では、DB内部の連番IDではなく `external_id` を永続的な外部連携キーとして保存してください。同じ `external_id` が再度届いた場合は新規作成ではなく更新です。

## 10. メール項目

`contact_email` と `login_email` は別項目です。

| 項目 | 用途 |
|---|---|
| contact_email | 連絡先、通知先メール |
| login_email | 外部ポータルへのログイン用メール |

両方が送信された場合も、片方で上書きせず、それぞれ独立して保存してください。

## 11. レスポンス仕様

正常時:

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

認証エラー:

```json
{
  "success": false,
  "error": "invalid_api_key"
}
```

HTTPステータスは、成功時は `200` または `201`、認証エラーは `401` または `403` を推奨します。

## 12. 外部連携ログと再送

AI側では外部送信結果を `integration_event_logs` に保存します。

管理画面:

```text
管理画面 > 外部連携ログ
```

できること:

- 送信成功・失敗の確認
- 連携先ごとの成功数、失敗数、再送対象数の確認
- 失敗ログ1件の手動再送
- 連携先ごとの失敗ログを最大10件ずつ再送
- 全連携先の失敗ログを最大10件ずつ再送

## 13. 自動再送URL

3.6.70以降、外部連携ログ画面で自動再送URLを発行できます。

```text
https://sengoku-ai.com/cron/external_integration_retry.php?token={token}&limit=10&notify=1
```

サーバーのcronで5分から15分間隔程度で実行してください。

例:

```cron
*/10 * * * * curl -fsS "https://sengoku-ai.com/cron/external_integration_retry.php?token={token}&limit=10&notify=1" >/dev/null
```

注意:

- 一度に大量送信しないため、`limit` は最大50件です。
- 通常運用では `limit=10` を推奨します。
- `notify=1` の場合、失敗が残ったときだけ管理者メールへ通知します。
- トークンを再発行すると、既存のcron URLは無効になります。

## 14. SSOとの関係

外部連携APIとSSOは別機能ですが、同じ `external_id` で紐づきます。

| 機能 | 目的 | 共通キー |
|---|---|---|
| 外部連携API | 代理店情報、権限、階層、LP URLを同期する | external_id |
| SSO | AI側にログイン済みの代理店を外部サイトへログインさせる | external_id |
| 共通ID | 外部サイト登録ユーザー、LP問い合わせ、紹介トークンを紐づける | common_user_id / external_id |

外部サイト側では、APIで受け取った `external_id` と、SSO JWT内の `external_id` を同じユーザーとして扱ってください。

## 15. 実装チェックリスト

外部サイト側:

- 受信用APIキーをサイトごとに発行できる
- `POST /api/integrations/agencies` を実装している
- `x-api-key` と `Authorization: Bearer` の両方を検証できる
- `connection_test` / `dry_run=true` を本登録せず処理できる
- `external_id` を一意キーとして保存している
- `parent_external_id` を保存して階層を再現できる
- `contact_email` と `login_email` を別々に保存している
- 成功時に `2xx` とJSONを返す
- SSOを使う場合、`external_id` で既存ユーザーと紐づける

AI側管理者:

- 外部API連携で連携先サイトを追加する
- AI側が発行した受信用APIキーを外部サイト側へ渡す
- 外部サイト側が発行した受信用APIキーをAI側へ登録する
- 接続テストを実行する
- 外部連携ログで送信結果を確認する
- 必要に応じて自動再送URLを発行し、cronへ登録する