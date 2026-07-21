# 千ノ国 代理店システム 共通顧客HUB 受入テスト表

作成日: 2026-07-21  
対象: `sengoku-ai.com` 代理店システム  
目的: 外部システム接続前に、データ不整合・誤本人統合・権利付与漏れ・APIキー事故を防ぐ

## 1. テスト前提

| 項目 | 条件 |
|---|---|
| 代理店システム | 最新版を適用済み |
| DBマイグレーション | 未適用0件 |
| 外部API連携 | 連携先サイトを1件以上登録 |
| AI受信用APIキー | 連携先ごとに発行済み |
| 外部システム受信用APIキー | 連携先から受領し登録済み |
| 共通顧客HUB | 有効 |
| 紹介V2 | 有効 |
| SSO | 使用する場合のみ有効 |

## 2. P0 必須テスト

### T-001 APIキーなしアクセス拒否

| 項目 | 内容 |
|---|---|
| 対象API | `GET /api/hierarchy.php` |
| 手順 | APIキーなしでアクセスする |
| 期待結果 | 401または403で拒否 |
| 合格条件 | 代理店情報・連絡先が返らない |

### T-002 不正APIキー拒否

| 項目 | 内容 |
|---|---|
| 対象API | `POST /api/common-users/resolve` |
| 手順 | 存在しないAPIキーで送信 |
| 期待結果 | 401 `INVALID_API_KEY` |
| 合格条件 | `common_user_id` が作成されない |

### T-003 スコープ不足拒否

| 項目 | 内容 |
|---|---|
| 対象API | `POST /api/common-users/resolve` |
| 手順 | `common_users:write` を持たないキーで送信 |
| 期待結果 | 403 `SCOPE_FORBIDDEN` |
| 合格条件 | DB更新なし |

### T-004 未検証メールで自動統合しない

| 項目 | 内容 |
|---|---|
| 対象API | `POST /api/common-users/resolve` |
| 手順 | 既存ユーザーと同じメールを `email_verified=false` で送信 |
| 期待結果 | 既存 `common_user_id` に自動統合しない |
| 合格条件 | `identity_match_status=unverified_candidate_not_auto_merged` または候補扱い |

### T-005 検証済みメールで統合できる

| 項目 | 内容 |
|---|---|
| 対象API | `POST /api/common-users/resolve` |
| 手順 | 既存ユーザーと同じメールを `email_verified=true` で送信 |
| 期待結果 | 既存 `common_user_id` を返す |
| 合格条件 | `matched_by=identity:email` または既存リンク一致 |

### T-006 冪等キー同一本文

| 項目 | 内容 |
|---|---|
| 対象API | `POST /api/referrals/confirm` |
| 手順 | 同じ `Idempotency-Key` と同じ本文を2回送信 |
| 期待結果 | 2回目は保存済みレスポンスを返す |
| 合格条件 | 取引・紹介関係が重複しない |

### T-007 冪等キー同一・本文違い

| 項目 | 内容 |
|---|---|
| 対象API | `POST /api/referrals/confirm` |
| 手順 | 同じ `Idempotency-Key` で異なる本文を送信 |
| 期待結果 | 409 `IDEMPOTENCY_CONFLICT` |
| 合格条件 | 後続本文でDB更新されない |

## 3. 共通IDテスト

### T-101 新規共通ID発行

```bash
curl -X POST "https://sengoku-ai.com/api/common-users/resolve" \
  -H "x-api-key: ${AI_INBOUND_API_KEY}" \
  -H "Content-Type: application/json" \
  -H "Idempotency-Key: test-common-resolve-001" \
  -d '{
    "system_key": "ai-art-school",
    "service_user_id": "test-user-001",
    "email": "test001@example.com",
    "email_verified": true,
    "create_if_missing": true
  }'
```

期待結果:

- `ok=true`
- `common_user_id` が `cu_` で始まる
- `created=true`

### T-102 外部ユーザーID再送で同じ共通IDを返す

同じ `system_key + service_user_id` で再送し、同じ `common_user_id` が返ること。

### T-103 共通ID詳細取得

```bash
curl "https://sengoku-ai.com/api/common-users/${COMMON_USER_ID}" \
  -H "Authorization: Bearer ${AI_INBOUND_API_KEY}"
```

期待結果:

- `common_user_id` が一致
- `system_links` に外部システムのリンクがある

## 4. 代理店階層テスト

### T-201 tree形式

```bash
curl "https://sengoku-ai.com/api/hierarchy.php?format=tree&include_contact=1" \
  -H "Authorization: Bearer ${AI_INBOUND_API_KEY}"
```

期待結果:

- `ok=true`
- `data` が配列
- 親代理店に `children` が存在する
- 各代理店に `agency_id` が存在する
- `parent_agency_id` が親代理店の `agency_id` と一致する

### T-202 flat形式

```bash
curl "https://sengoku-ai.com/api/hierarchy.php?format=flat&root_code=${AGENCY_ID}" \
  -H "Authorization: Bearer ${AI_INBOUND_API_KEY}"
```

期待結果:

- 指定代理店と配下だけが返る
- 内部IDではなく `agency_id` で外部保存できる

## 5. 紹介テスト

### T-301 capture成功

```bash
curl -X POST "https://sengoku-ai.com/api/referrals/capture" \
  -H "x-api-key: ${AI_INBOUND_API_KEY}" \
  -H "Content-Type: application/json" \
  -H "Idempotency-Key: test-ref-capture-001" \
  -d '{
    "system_key": "ai-art-school",
    "referral_token": "${REFERRAL_TOKEN}",
    "session_key": "test-session-001",
    "landing_url": "https://example.com/lp",
    "destination_url": "https://example.com/register"
  }'
```

期待結果:

- `ok=true`
- `canonical_referral_token` が返る
- `agency_id` が返る
- `referral_session_key` が返る

### T-302 confirm成功

```bash
curl -X POST "https://sengoku-ai.com/api/referrals/confirm" \
  -H "x-api-key: ${AI_INBOUND_API_KEY}" \
  -H "Content-Type: application/json" \
  -H "Idempotency-Key: test-ref-confirm-001" \
  -d '{
    "system_key": "ai-art-school",
    "service_user_id": "test-user-001",
    "referral_session_key": "test-session-001",
    "order_id": "test-order-001",
    "product_code": "ai_art_trial",
    "payment_status": "paid",
    "entitlement_status": "granted",
    "amount": 0,
    "currency": "JPY"
  }'
```

期待結果:

- `ok=true`
- `common_user_id` が返る
- `agency_id` が返る
- `relation` が返る
- `transaction` が返る

### T-303 旧紹介コードのエイリアス解決

`ref` または `referral_code` に旧コードを入れて送信し、`canonical_referral_token` が正規トークンになること。

## 6. 担当代理店・販売担当テスト

### T-401 4ロール保存

`POST /api/referrals/confirm` に以下を指定します。

```json
{
  "registration_referrer_agency_id": "agent001",
  "assigned_agency_id": "dir001",
  "sales_agent_id": "advisor001",
  "closing_agent_id": "director001"
}
```

期待結果:

- 取引行に4項目が保存される
- 未指定項目があってもAPIは失敗しない
- 既存の登録紹介元を意図せず上書きしない

## 7. Webhook送信テスト

### T-501 接続テスト

管理画面「外部API連携」から接続テストを実行します。

期待結果:

- 外部システムに `connection_test` が届く
- HTTP 2xxを返す
- 代理店システムの接続テスト欄が成功になる
- `integration_event_logs` に成功ログが残る

### T-502 認証失敗

外部システム受信用APIキーを間違えて登録し、接続テストを実行します。

期待結果:

- 外部システムが401または403を返す
- 代理店システム側に失敗ログが残る
- 秘密情報がログに平文で残らない

### T-503 HMAC検証

HMAC設定がある連携先で、外部システム側が以下を検証します。

- `X-SenNoKuni-Timestamp`
- `X-SenNoKuni-Nonce`
- `X-SenNoKuni-Signature`
- raw JSON body

期待結果:

- 正しい署名は受理
- 本文改ざん時は拒否
- 古いtimestampは拒否
- 同じnonce再利用は拒否

## 8. SSOテスト

### T-601 JWKS取得

```bash
curl "https://sengoku-ai.com/api/sso/jwks.php"
```

期待結果:

- `keys` 配列が返る
- `kid` がJWTヘッダーと一致する
- 公開鍵のみ返り、秘密鍵は返らない

### T-602 SSO起動

代理店システムへログインし、外部ポータル連携ボタンをクリックします。

期待結果:

- 外部システムのSSO受信URLへリダイレクトされる
- JWTの `alg=RS256`
- `iss=https://sengoku-ai.com`
- `aud` が連携先設定と一致
- `sub` / `external_id` が `agent_code`
- `exp` が期限内

### T-603 リプレイ拒否

同じJWTを再利用します。

期待結果:

- 外部システム側で `jti` 再利用として拒否する

## 9. 障害・復旧テスト

### T-701 外部システム停止時

外部受信APIを一時的に500にしてWebhookを送ります。

期待結果:

- 代理店システム側に失敗ログが残る
- 再送対象として追跡できる
- PIIやAPIキーがログに平文で残らない

### T-702 DBエラー時の部分更新防止

紹介confirm中に意図的にDBエラーを発生させます。

期待結果:

- 共通ID、紹介関係、取引の一部だけが残らない
- APIは500を返す
- ロールバックされる

## 10. 本番リリース判定

| 項目 | 合格条件 |
|---|---|
| APIキー | 連携先ごとに別キー |
| 認証 | 不正キー・スコープ不足を拒否 |
| 共通ID | 新規発行、既存リンク一致、未検証候補の安全処理 |
| 代理店ID | 外部保存値が `agency_id=agent_code` |
| 紹介 | capture/confirmが成功し、冪等 |
| 担当 | 4ロールが保存・参照できる |
| Webhook | 接続テスト成功、失敗ログあり |
| SSO | JWT検証、aud/iss/exp/jti確認 |
| ログ | PII/APIキーが平文で残らない |
| 復旧 | 再送または手動復旧手順がある |

すべて合格してから、本番連携を開始してください。

