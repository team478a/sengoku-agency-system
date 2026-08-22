# 外部開発者へ渡す資料

更新日: 2026-08-17

## 渡すファイル

外部開発者へは、まず次の1ファイルを渡してください。

```text
docs/integration/EXTERNAL_DEVELOPER_GUIDE.md
```

運用担当者向けに手順も一緒に渡す場合は、次のファイルも添付してください。

```text
docs/integration/EXTERNAL_INTEGRATION_SETUP_FLOW_20260803.md
```

## 事前に決めること

外部開発者へ渡す前に、管理者側で次を決めてください。

| 項目 | 説明 |
| --- | --- |
| 連携先名 | 例: 戦国パスポート、ショッピングサイト、AIアート教室 |
| `site_key` | 連携先を識別する固定キー。例: `sengoku-passport` |
| `project_key` | 商品、LP、案件を識別するキー。プロジェクト管理のスラッグを使います。 |
| 送信先URL | 代理店システムから外部サービスへWebhookを送るURL |
| 連携先発行APIキー | 外部サービス側で発行し、代理店システムへ登録するキー |

## 外部開発者へ伝える重要事項

- Base URL は `https://sengoku-ai.com` です。
- 正式な共通顧客ID解決APIは `POST /api/common-users/resolve` です。
- `/api/v2/common-users/resolve` は外部公開用の正式パスではありません。
- API認証は `x-api-key` または `Authorization: Bearer` です。
- 商品やLPの区別は `project_key` で行います。
- 代理店の識別には `agent_code` を使います。内部DBの `id` は使いません。
- 紹介流入を取る場合は `POST /api/referrals/capture` を使います。
- 登録、購入、申込が完了したら `POST /api/referrals/confirm` を使います。
- SSOはRS256署名のJWTをJWKSで検証します。

## APIキーの共有方法

外部サービスごとに、2種類のキーがあります。

| キー | 誰が発行するか | 誰が使うか |
| --- | --- | --- |
| 代理店システム発行APIキー | sengoku-ai.com | 外部サービスが sengoku-ai.com に送信するとき |
| 連携先発行APIキー | 外部サービス | sengoku-ai.com が外部サービスへWebhook送信するとき |

商品が増えてもAPIキーを増やす必要はありません。同じ外部サービスなら1つのAPIキーで、`project_key` と `product_code` で区別してください。

## 接続確認

接続確認では次を見ます。

1. 外部API連携画面で接続テストが成功すること
2. 外部連携ログに成功ログが残ること
3. `common-users/resolve` が成功すること
4. `referrals/capture` が `session_key` を返すこと
5. `referrals/confirm` が成果を確定できること

## 追加で必要な場合

SSO連携が必要な場合は、以下も外部開発者へ渡してください。

- SSO起動URL: `https://sengoku-ai.com/agent/sso_launch.php?client={site_key}`
- JWKS URL: `https://sengoku-ai.com/api/sso/jwks.php`
- 署名方式: RS256
- 必須検証: `iss`, `aud`, `exp`, `jti`
