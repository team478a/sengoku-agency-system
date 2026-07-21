# 代理店システム SSO連携仕様書

Version: 3.6.45

## 1. 目的

`sengoku-ai.com` の代理店システムをログイン元として、外部ポータルへパスワード入力なしでログインさせるための仕様です。

この仕様では、代理店システムを **IdP**、外部ポータルを **RP** と呼びます。

| 名称 | 役割 | 例 |
| --- | --- | --- |
| IdP | ログイン元。JWTを発行する | `sengoku-ai.com` |
| RP | JWTを受け取りログインさせる | `sengoku-rr.com` |

## 2. 既存APIキー連携との違い

SSOは、既存の代理店同期APIキーとは別の仕組みです。

| 用途 | 認証方式 | 使う値 |
| --- | --- | --- |
| 代理店同期API | `x-api-key` または `Authorization: Bearer` | 双方向連携用APIキー |
| 階層取得API | `x-api-key` または `Authorization: Bearer` | sengoku-ai.com受信用APIキー |
| SSOログイン | `RS256` 署名付きJWT | IdPの秘密鍵と公開鍵 |

APIキーはデータ送受信用、SSO署名鍵はログイン用です。混同しないでください。

## 3. 全体フロー

1. 代理店ユーザーが `sengoku-ai.com` の代理店マイページにログインします。
2. ユーザーが「外部ポータルを開く」を押します。
3. `sengoku-ai.com` が短時間だけ有効なJWTをサーバー側で発行します。
4. ブラウザを `sengoku-rr.com` のSSO受信URLへリダイレクトします。
5. `sengoku-rr.com` がJWT署名、`iss`、`aud`、`exp`、`jti` を検証します。
6. `sub` の代理店コードをキーにRR側ユーザーを特定し、通常のログインセッションを作成します。
7. RR側の代理店ポータルへ遷移します。

## 4. IdP側エンドポイント

### SSO起動URL

```text
GET https://sengoku-ai.com/agent/sso_launch.php?aud=sengoku-rr
```

このURLは代理店ログイン中のみ利用できます。

未ログインの場合は代理店ログイン画面へ遷移します。

### JWKS公開URL

```text
GET https://sengoku-ai.com/api/sso/jwks.php
```

RR側がJWT署名検証に使う公開鍵を取得するURLです。

レスポンス例:

```json
{
  "keys": [
    {
      "kty": "RSA",
      "use": "sig",
      "kid": "sso-20260709120000-abcd1234",
      "alg": "RS256",
      "n": "...",
      "e": "AQAB"
    }
  ]
}
```

## 5. RP側に必要なエンドポイント

RR側には、以下のSSO受信エンドポイントが必要です。

```text
GET https://sengoku-rr.com/agency/sso?token={JWT}
```

`sengoku-ai.com` の管理画面では、RR側SSO受信URLとして以下のどちらでも登録できます。

```text
https://sengoku-rr.com
https://sengoku-rr.com/agency/sso
```

ドメインのみ登録された場合、AI側は `/agency/sso` を自動で付与します。

## 6. JWT仕様

署名方式:

```text
RS256
```

JWTヘッダー:

```json
{
  "typ": "JWT",
  "alg": "RS256",
  "kid": "sso-20260709120000-abcd1234"
}
```

JWTペイロード例:

```json
{
  "iss": "https://sengoku-ai.com",
  "sub": "dir260b6d6e",
  "external_id": "dir260b6d6e",
  "aud": "sengoku-rr",
  "iat": 1783569600,
  "exp": 1783569660,
  "jti": "b5f0d0b6...",
  "role_level": 2,
  "role_label": "ディレクター",
  "agency_name": "yamayama",
  "contact_name": "yamada",
  "contact_email": "example@example.com",
  "actor_id": "dir260b6d6e",
  "actor_name": "yamada",
  "actor_email": "example@example.com"
}
```

| Claim | 必須 | 内容 |
| --- | --- | --- |
| `iss` | 必須 | 発行者。通常は `https://sengoku-ai.com` |
| `sub` | 必須 | 代理店コード。RR側ではこの値でユーザーを紐づける |
| `external_id` | 必須 | `sub` と同じ代理店コード |
| `aud` | 必須 | 連携先識別子。初期値は `sengoku-rr` |
| `iat` | 必須 | 発行時刻 |
| `exp` | 必須 | 有効期限。発行から60秒 |
| `jti` | 必須 | ワンタイム利用判定用ID |
| `role_level` | 任意 | 代理店階層 |
| `role_label` | 任意 | 代理店階層名 |
| `agency_name` | 任意 | 代理店名 |
| `contact_name` | 任意 | 担当者名 |
| `contact_email` | 任意 | 連絡先メール |
| `actor_*` | 任意 | 操作者情報。現時点では代理店本人情報 |
| `return_to` | 任意 | RR側ログイン後の遷移先。内部パスのみ許可 |

## 7. RR側の検証要件

RR側は以下を検証してください。

- JWKSまたは登録済み公開鍵で署名を検証する
- `alg` が `RS256` であること
- `iss` が `https://sengoku-ai.com` であること
- `aud` が `sengoku-rr` であること
- `exp` が期限切れでないこと
- `iat` が未来すぎないこと
- `jti` が未使用であること
- `sub` に一致する代理店がRR側に存在すること
- 代理店が停止中でないこと

時計ずれ対策として、`iat` と `exp` は30秒から60秒程度の許容を推奨します。

## 8. jtiの保存

RR側では、JWTの再利用を防ぐため `jti` を保存してください。

例:

```sql
CREATE TABLE sso_used_jti (
    id INT AUTO_INCREMENT PRIMARY KEY,
    jti VARCHAR(128) NOT NULL UNIQUE,
    sub VARCHAR(191) NOT NULL,
    aud VARCHAR(100) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sso_used_jti_expires (expires_at)
);
```

一度使った `jti` が再度来た場合は拒否してください。

## 9. 代理店コードの扱い

SSOでは、既存の代理店同期APIと同じく代理店コードを外部IDとして扱います。

```text
external_id = agent_code
sub = agent_code
```

RR側では、この値を一意キーとして代理店アカウントに紐づけてください。

## 10. 未連携・停止中の扱い

RR側に `sub` と一致する代理店が存在しない場合:

```text
/login?error=agency_not_linked
```

RR側で代理店が停止中の場合:

```text
/login?error=agency_inactive
```

JWT期限切れの場合:

```text
/login?error=sso_expired
```

JWT再利用の場合:

```text
/login?error=sso_replayed
```

## 11. セキュリティ注意点

- 秘密鍵は `sengoku-ai.com` 側だけで保持します。
- RR側へ渡すのは公開鍵またはJWKS URLだけです。
- JWTは60秒で期限切れにします。
- JWTは一度しか使えないようRR側で `jti` を保存してください。
- SSO受信後はすぐに通常セッションを作成し、URL上の `token` を残さない画面へリダイレクトしてください。
- SSO受信エンドポイントでは `Referrer-Policy: no-referrer` の利用を推奨します。

## 12. 実装担当範囲

### sengoku-ai.com側

- SSO設定画面
- 署名鍵の発行
- JWKS公開
- 代理店ログイン中のJWT発行
- RR側SSO受信URLへのリダイレクト

### sengoku-rr.com側

- `/agency/sso` の受信エンドポイント
- JWKSまたは公開鍵によるJWT検証
- `sub` と代理店アカウントの紐づけ
- `jti` の一度きり利用チェック
- 通常ログインセッションの作成

## 13. テスト項目

- 正常なJWTでRR側にログインできる
- 期限切れJWTは拒否される
- 同じJWTの再利用は拒否される
- `aud` が違うJWTは拒否される
- `iss` が違うJWTは拒否される
- 未登録の `sub` は `agency_not_linked` になる
- 停止中代理店は `agency_inactive` になる
- 鍵を再発行した場合、RR側が新しい公開鍵で検証できる
