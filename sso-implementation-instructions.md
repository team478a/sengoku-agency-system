# 代理店システム SSO 実装指示書

## 1. 目的

このSSOは、`sengoku-ai.com` の代理店システムをログイン元として、`sengoku-rr.com` や今後追加される外部システムへ、代理店ユーザーをパスワード入力なしでログインさせるための仕組みです。

今後、連携先サイトは1つに限定しません。  
外部サイトごとに「連携先サイト」を登録し、代理店マイページから対象サイトを選んでSSOログインします。

## 2. 役割分担

### sengoku-ai.com 側

- 代理店ユーザーのログイン状態を確認する
- 連携先サイトごとにSSO起動URLを発行する
- 代理店情報を含むJWTをRS256で署名する
- 連携先サイトのSSO受信URLへ `token` パラメータ付きでリダイレクトする
- JWKS URLで公開鍵を提供する

### 外部サイト側

- SSO受信エンドポイントを用意する
- `token` パラメータのJWTを検証する
- `iss`、`aud`、`exp`、署名、`jti` を検証する
- `external_id` を代理店の一意IDとしてユーザーを作成・更新する
- 認証後、外部サイトの管理画面またはポータルへログイン状態で遷移する

## 3. sengoku-ai.com 側の管理画面

管理者画面に独立ページとして以下を追加しています。

```text
管理画面 > SSO連携
```

このページで以下を管理します。

- SSO共通設定
- SSO署名鍵
- 連携先サイト一覧
- 連携先サイトの追加・編集・削除

## 4. SSO共通設定

SSO署名鍵は全連携先サイトで共通です。

| 項目 | 内容 |
|---|---|
| iss | JWT発行者。通常は `https://sengoku-ai.com` |
| JWKS URL | 外部サイトが公開鍵を取得するURL |
| 署名方式 | RS256 |
| 鍵ID | JWTヘッダーの `kid` に入る値 |

JWKS URL:

```text
https://sengoku-ai.com/api/sso/jwks.php
```

外部サイト側はこのJWKS URLから公開鍵を取得し、JWT署名を検証してください。

## 5. 連携先サイト設定

外部サイトごとに以下を登録します。

| 項目 | 説明 | 例 |
|---|---|---|
| サイトキー | AI側で連携先を選ぶためのキー。URLの `client` に使う | `sengoku-rr` |
| 連携先名 | 管理画面・代理店マイページに表示する名前 | `sengoku-rr.com` |
| aud | JWTの受信者識別子。外部サイト側で検証する | `sengoku-rr` |
| SSO受信URL | 外部サイト側のSSO受信エンドポイント | `https://sengoku-rr.com/agency/sso` |
| 状態 | 有効または停止 | 有効 |
| 表示順 | 代理店マイページでの並び順 | `10` |

SSO受信URLは、ドメインだけを入力した場合、自動で `/agency/sso` が付与されます。

例:

```text
https://sengoku-rr.com
```

実際の送信先:

```text
https://sengoku-rr.com/agency/sso
```

## 6. SSO起動URL

代理店マイページから以下の形式で起動します。

```text
https://sengoku-ai.com/agent/sso_launch.php?client={サイトキー}
```

例:

```text
https://sengoku-ai.com/agent/sso_launch.php?client=sengoku-rr
```

このURLはログイン済み代理店のみ利用できます。未ログインの場合は代理店ログイン画面へ誘導されます。

## 7. 外部サイト側の受信エンドポイント

外部サイト側は、以下のような受信エンドポイントを用意してください。

```text
GET /agency/sso?token={JWT}
```

独自のパスでも構いません。  
その場合は `sengoku-ai.com` のSSO連携設定で、完全な受信URLを登録してください。

## 8. JWT仕様

### Header

```json
{
  "typ": "JWT",
  "alg": "RS256",
  "kid": "sso-20260708000000-xxxxxxxx"
}
```

### Payload

```json
{
  "iss": "https://sengoku-ai.com",
  "sub": "dir260b6d6e",
  "external_id": "dir260b6d6e",
  "aud": "sengoku-rr",
  "iat": 1783526400,
  "exp": 1783526460,
  "jti": "random-unique-token",
  "role_level": 2,
  "role_label": "ディレクター",
  "agency_name": "山田商事",
  "contact_name": "山田 太郎",
  "contact_email": "yamada@example.com",
  "actor_id": "dir260b6d6e",
  "actor_name": "山田 太郎",
  "actor_email": "yamada@example.com",
  "client_key": "sengoku-rr",
  "client_name": "sengoku-rr.com"
}
```

### 主な検証項目

外部サイト側では必ず以下を検証してください。

| 検証項目 | 内容 |
|---|---|
| 署名 | JWKSから取得した公開鍵でRS256署名を検証 |
| iss | `https://sengoku-ai.com` と一致すること |
| aud | 外部サイト側で期待する値と一致すること |
| exp | 有効期限内であること |
| jti | 再利用されていないこと |
| external_id | 代理店ユーザーの一意キーとして保存すること |

JWTの有効期限は短く、現在は60秒です。

## 9. ユーザー紐付け

外部サイト側では `external_id` を代理店ユーザーの一意キーとして扱ってください。

推奨:

```text
external_id = agent_code
```

例:

```text
dir260b6d6e
agent7_8573
```

DB内部の連番IDではなく、代理店コードを使います。  
代理店コードは外部連携・SSOの識別子として継続利用する前提です。

## 10. 外部連携APIとの関係

SSOと外部連携APIは、同じ代理店コードを共通キーとして連携します。

```text
外部連携APIの external_id
=
SSO JWTの external_id
=
sengoku-ai.com の agent_code
```

役割は以下の通りです。

| 仕組み | 役割 |
|---|---|
| 外部連携API | 代理店情報、権限、親子関係、LP URL、SSO起動URLを外部システムへ渡す |
| SSO | ログイン済み代理店ユーザーを外部システムへログインさせる |

外部連携APIの代理店同期POSTには、以下のように `sso_urls` が含まれます。

```json
{
  "external_id": "dir260b6d6e",
  "role_level": 2,
  "role_label": "ディレクター",
  "sso_urls": [
    {
      "client_key": "sengoku-rr",
      "client_name": "sengoku-rr.com",
      "audience": "sengoku-rr",
      "url": "https://sengoku-ai.com/agent/sso_launch.php?client=sengoku-rr"
    }
  ]
}
```

階層取得APIでも、`include_sso=1` を指定すると各代理店データに `sso_urls` が含まれます。

```text
GET https://sengoku-ai.com/api/hierarchy.php?format=flat&include_contact=1&include_sso=1
```

## 11. 初回ログイン時の処理

外部サイト側で `external_id` のユーザーが存在しない場合は、JWTの情報を使ってユーザーを作成してください。

最低限保存する項目:

- external_id
- role_level
- role_label
- agency_name
- contact_name
- contact_email

既に存在する場合は、名前・メール・権限などを更新してください。

## 12. 新しい外部サイトを追加する手順

1. 外部サイト側でSSO受信エンドポイントを作成する
2. 外部サイト側でJWKS URLを設定する
3. 外部サイト側で期待する `iss` と `aud` を設定する
4. `sengoku-ai.com` 管理画面の「SSO連携」で連携先サイトを追加する
5. 代理店マイページに新しい外部ポータルボタンが表示されることを確認する
6. 実際にSSOログインできることをテストする

## 13. sengoku-rr.com の設定例

### sengoku-ai.com 側

| 項目 | 値 |
|---|---|
| サイトキー | `sengoku-rr` |
| 連携先名 | `sengoku-rr.com` |
| aud | `sengoku-rr` |
| SSO受信URL | `https://sengoku-rr.com/agency/sso` |
| 状態 | 有効 |

### sengoku-rr.com 側

| 項目 | 値 |
|---|---|
| JWKS URL | `https://sengoku-ai.com/api/sso/jwks.php` |
| issuer | `https://sengoku-ai.com` |
| audience | `sengoku-rr` |
| external_id | JWTの `external_id` |

## 14. エラー時の扱い

外部サイト側でJWT検証に失敗した場合は、ログインさせずエラー画面を表示してください。

主なエラー:

- token未指定
- 署名検証失敗
- 有効期限切れ
- audience不一致
- issuer不一致
- jti再利用
- external_id未指定

## 15. セキュリティ注意点

- 秘密鍵は `sengoku-ai.com` 外へ渡さない
- 外部サイト側にはJWKS URLまたは公開鍵のみ渡す
- JWTはURLに含まれるため、有効期限は短くする
- 外部サイト側では `jti` を一定時間保存し、同じJWTの再利用を拒否する
- SSO受信URLはHTTPS必須
- 連携停止したサイトは `sengoku-ai.com` 管理画面で状態を「停止」にする

## 16. 実装完了条件

外部サイト側の実装完了条件は以下です。

- JWKS URLから公開鍵を取得できる
- RS256署名を検証できる
- `iss` と `aud` を検証できる
- `exp` を検証できる
- `jti` の再利用を拒否できる
- `external_id` でユーザーを作成・更新できる
- SSO成功後に外部ポータルへログイン状態で遷移できる
- エラー時に未ログイン状態のまま安全に止まる
