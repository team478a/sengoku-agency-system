# 千ノ国代理店システム PR-A0 事前確認レポート

作成日: 2026-08-22  
対象リポジトリ: `team478a/sengoku-agency-system`  
基準ブランチ: `main`  
基準コミット: `0db031b6974ac712bf81962e66c02d60f2d590f4`  
基準バージョン: `3.6.161`

## 0. 作業範囲

本レポートは、次工程実装指示書の `PR-A0: 現行環境・マイグレーション確認` として作成した。

今回実施したのはローカルリポジトリの read-only 確認と、レポート作成のみである。コード変更、ブランチ作成、DBマイグレーション作成・適用、本番・ステージングデータ変更、機能フラグ変更、外部送信開始、マージ、デプロイは行っていない。

## 1. サマリー

`main` は指定された基準コミットと一致しており、ローカル作業ツリーは確認時点でクリーンだった。`VERSION` は `3.6.161`。

共通ID、紹介、外部連携、Outbox、購入者利用権、顧客SSOの基盤は既に存在する。一方、次工程の指示書が求める「一般ユーザーと代理店資格の完全分離」「販売事実Inboxの正式契約」「商品・報酬対象ルール」「手動報酬CSV・支払申請」は、既存実装の上に不足分を追加する必要がある。

本番DB接続情報はローカルリポジトリに存在しなかったため、実DB上の `schema_migrations`、件数、cron実行履歴、Outbox/DLQ滞留件数、JWKSの実稼働状態、外部連携先の実設定値は確認できていない。秘密値は確認・記載していない。

## 2. リポジトリ状態

| 項目 | 確認結果 |
|---|---|
| 対象リポジトリ | `team478a/sengoku-agency-system` |
| 現在ブランチ | `main` |
| 最新コミット | `0db031b6974ac712bf81962e66c02d60f2d590f4` |
| 最新コミット概要 | `Merge agency candidate and integration updates` |
| origin/main | `main` と一致 |
| 未コミット変更 | 確認時点ではなし。本レポート追加後は本ファイルのみ差分 |
| ローカルバージョン | `3.6.161` |

## 3. PR-A0 確認項目

### 3.1 配備中のアプリケーションバージョン

ローカル `main` の `VERSION` は `3.6.161`。本番に実際に配備中のバージョンは、サーバー側の管理画面または本番ファイルで別途確認が必要。

### 3.2 schema_migrations の適用状況

ローカルのマイグレーションファイル数は 97 件。番号上の最新側には `3.6.150.sql`、`3.6.151.sql`、`3.6.155.sql`、`3.6.156.sql` が存在する。

本番DBの `schema_migrations` 適用状況は、ローカルに `config/database.php` がないため未確認。未適用がある場合は、本指示どおり自動適用せず、対象・影響・バックアップ・ロールバックを確認してから別作業で実施する。

### 3.3 customer_entitlements と関連列・制約・索引

`config/migrations/3.6.150.sql` に `customer_entitlements` 作成定義がある。コード上は `includes/functions.php` の `saveCustomerEntitlement()` と、管理画面 `admin/customer_entitlements.php` が存在する。

ただし、本番DBで実テーブル・制約・索引が作成済みかは未確認。

### 3.4 共通ユーザー関連テーブルと一意制約

`config/migrations/3.6.59.sql` に以下の基礎テーブルが存在する。

- `common_users`
- `service_user_mappings`
- `agency_customer_relations`
- `integration_idempotency_keys`
- `integration_event_logs`

`config/migrations/3.6.72.sql` に共通顧客HUB拡張として以下が存在する。

- `user_identities`
- `system_account_links`
- `agent_touchpoints`
- `account_merge_logs`

コード上は `src/CommonIdentity/CommonUserResolveService.php`、`src/CommonIdentity/CommonUserRepository.php`、`src/CommonIdentity/SystemAccountLinkRepository.php` が解決処理の中心。

注意点として、既存APIの入力名は主に `system_key` / `service_key` で、今回指示書の最低入力である `service_code` は現時点の正規化処理に含まれていない。互換性を壊さず `service_code` を受ける追加が必要。

### 3.5 外部連携先設定、API scope、IP制限の実値

`config/migrations/3.6.51.sql`、`3.6.55.sql`、`3.6.78.sql` に `external_partner_sites` と連携先別APIキー、scope、HMAC、IP制限関連の列がある。

管理画面は `admin/external_partners.php`、`admin/external_partner_wizard.php`、`admin/settings.php` に存在する。

実値は秘密情報を含む可能性があるため記載しない。本番DB未接続のため、現時点では設定済み件数・scope・IP制限の実値は未確認。

### 3.6 SSO署名鍵とJWKSの稼働状態

実装場所は以下。

- `api/sso/customer-token/index.php`
- `api/sso/customer-token.php`
- `api/sso/jwks.php`
- `includes/functions.php` の `buildCustomerSsoJwt()`

RS256 JWT発行とJWKSエンドポイントはコード上存在する。秘密鍵・公開鍵の本番設定値、JWKSが本番で正常応答しているかは未確認。秘密鍵は表示・記載していない。

### 3.7 cron / Outbox / DLQ の直近実行記録と滞留件数

実装場所は以下。

- `cron/external_integration_retry.php`
- `src/Integration/Outbox/OutboxRepository.php`
- `src/Integration/Outbox/OutboxClaimService.php`
- `src/Integration/Outbox/DeadLetterService.php`
- `admin/integration_outbox.php`
- `admin/integration_logs.php`

Outbox、claim locking、DLQの仕組みは存在する。本番のcron登録状況、直近実行時刻、滞留件数、DLQ件数はDB・サーバーログ未接続のため未確認。

### 3.8 common_user_id の総数、未解決数、重複候補数

本番DB未接続のため未確認。次工程に入る前に、少なくとも `common_users`、`service_user_mappings`、`system_account_links`、`user_identities` の件数と、競合候補の抽出結果を確認する必要がある。

### 3.9 customer_entitlements の状態別件数

本番DB未接続のため未確認。ステータス別件数、期限切れ、取消済み、返金連動取消の確認が必要。

### 3.10 最新mainのCI結果

GitHub CLI が未ログインのため、GitHub Actions の最新結果は取得できなかった。

ローカルで実施できた検証結果は以下。

| 検証 | 結果 |
|---|---|
| PHP syntax lint | 成功 |
| CSV contract tests | DB接続環境変数未設定のためスキップ |
| composer validate --strict | Composer未検出のため未実行 |
| PHPUnit | `vendor/bin/phpunit` 未検出のため未実行 |
| PHPStan | `vendor/bin/phpstan` 未検出のため未実行 |
| GitHub Actions確認 | `gh auth login` 未実施のため未確認 |

## 4. 関連機能の実装場所と書込み経路

### 4.1 共通ユーザー解決

- API: `api/common-users/index.php`
- 互換URL: `api/common-users/resolve/index.php`
- 中心処理: `src/CommonIdentity/CommonUserResolveService.php`
- 入力正規化: `src/CommonIdentity/CommonUserInputNormalizer.php`
- 書込み先: `common_users`、`service_user_mappings`、`system_account_links`、`user_identities`、`agency_customer_relations`

現状では `system_key` / `service_key` と `external_user_id` を中心に解決する。`service_code` は別名として受ける追加が必要。

### 4.2 紹介流入・紹介確定

- `api/referrals/capture/index.php`
- `api/referrals/confirm/index.php`
- `api/v2/referral-sessions/index.php`
- `api/v2/referral-tokens/index.php`

自然流入、紹介流入、紹介元未確定の区別は既存設計上存在するが、今回指示書の出力例である `referral_status` との契約固定は追加確認が必要。

### 4.3 外部イベント受信

- `api/integrations/events/index.php`
- `includes/functions.php` の `saveCustomerTransaction()`
- `includes/functions.php` の `saveCustomerEntitlement()`

既存実装は `order.created`、`order.completed`、`purchase.completed`、`payment.succeeded`、`payment.failed`、`payment.refunded`、`entitlement.granted`、`entitlement.revoked`、`application.completed` を扱う。

今回指示書が求める `sale.completed`、`sale.refunded`、`sale.cancelled` を中心にした「販売事実Inbox」は、既存の `customer_transactions` と役割が近いが、原始記録・payload_hash・event_version・amount_minor・eligibility_status・referral_snapshot などの正式契約が不足している。

### 4.4 購入者利用権

- `customer_entitlements`
- `admin/customer_entitlements.php`
- `api/integrations/events/index.php`
- `api/sso/customer-token/index.php`

利用権付与は存在するが、販売事実受信と権限付与の境界が混在している箇所がある。PR-A3/A6では「販売事実」と「利用権付与」を分けて検証する必要がある。

### 4.5 SSO

- `admin/sso_settings.php`
- `api/sso/jwks.php`
- `api/sso/customer-token/index.php`
- `includes/functions.php` の `buildCustomerSsoJwt()`

顧客SSOはRS256/JWKS形式の実装がある。連携先ごとの `aud`、鍵ローテーション、無効化、リプレイ防止の本番設定確認が必要。

### 4.6 外部連携・Outbox

- `admin/external_partners.php`
- `admin/external_partner_wizard.php`
- `admin/integration_outbox.php`
- `admin/integration_logs.php`
- `src/Integration/Outbox/*`
- `cron/external_integration_retry.php`

連携先別APIキー、接続テスト、ログ、再送基盤は存在する。外部送信フラグをONにする前に、ステージングでDLQ・再送・二重送信防止の確認が必要。

## 5. Feature Flag と設定状態

コード上で確認できる主なフラグは `includes/functions.php` の `getCommonIdFeatureFlags()` に集約されている。

確認できた主なフラグ:

- `common_id_enabled`
- `common_hub_enabled`
- `common_hub_read_enabled`
- `common_hub_write_enabled`
- `referral_v2_enabled`
- `external_registration_capture_enabled`
- `referral_token_api_enabled`
- `passport_integration_enabled`
- `shopping_integration_enabled`
- `wallet_integration_enabled`
- `ai_art_integration_enabled`
- `common_hub_verified_identity_only`
- `external_partner_outbox_enabled`
- `external_partner_hmac_enabled`

次工程指示書が求める以下のフラグは、現時点で同名の独立フラグとしては確認できない。

- 共通ユーザー自動作成
- 販売事実受信
- 報酬判定
- 購入者利用権付与
- 購入者SSO
- 外部イベント配送
- 報酬CSVインポート
- 支払申請

また、`3.6.78.sql` では `external_partner_outbox_enabled` と `external_partner_hmac_enabled` が `1` で投入される。今回指示書では既定値OFFが原則のため、既存運用との互換性を確認したうえで、今後の新規フラグはOFF既定にする必要がある。

## 6. DB変更の追加必要性

PR-A0時点ではDB変更は行っていない。

今後のPRでは、少なくとも以下の追加または既存テーブル拡張が必要になる可能性が高い。

1. 販売事実Inbox用テーブル
   - `source_system_key`
   - `event_id`
   - `event_version`
   - `event_type`
   - `occurred_at`
   - `common_user_id`
   - `order_id`
   - `order_item_id`
   - `product_code`
   - `amount_minor`
   - `currency`
   - `eligibility_status`
   - `referral_snapshot`
   - `correlation_id`
   - `payload_hash`

2. 商品・報酬対象ルール
   - `(source_system_key, product_code)` の複合一意
   - 報酬対象区分
   - 利用権種別
   - 取消・返金時処理

3. 報酬CSV・報酬元帳・支払申請
   - CSVインポート履歴
   - 報酬元帳
   - 支払申請
   - 支払履歴
   - 調整仕訳

4. Feature Flag追加
   - 既定OFF
   - 生成と配送、受信と業務適用を分離

## 7. 他システムとのAPI・認証・イベント契約

### 7.1 認証

コード上は `src/Shared/Auth/ApiKeyAuthenticator.php` により、`x-api-key` と `Authorization: Bearer` を中心に認証する。`api/v2/bootstrap.php` では HMAC系ヘッダーも許可ヘッダーとして扱っている。

ただし、HMAC署名の必須検証が全対象APIで実際に強制されているかは、追加確認が必要。次工程の `APIキー／HMACまたは現行の承認済み認証方式` の扱いは、既存契約を壊さない形で整理する必要がある。

### 7.2 共通ユーザー解決API

既存URL:

- `POST /api/common-users/resolve`
- `POST /api/common-users`

既存入力は `system_key` / `service_key` と `external_user_id` が中心。今回指示書の `service_code` を正式入力にする場合は、既存互換を維持しつつ別名受け入れが必要。

### 7.3 販売事実イベント

既存の `POST /api/integrations/events` はあるが、今回指示書の販売事実正式契約とは項目名・イベント名・保存方針に差分がある。

特に、既存は利用権付与まで同じ経路で進める箇所があるため、PR-A3では「原始販売事実の保存」と「報酬判定・利用権付与」を分離する必要がある。

## 8. 不明点・不整合・重複機能・不要候補

### 8.1 不明点

- 本番DBの `schema_migrations` 実適用状態
- 本番のFeature Flag実値
- cron登録と最終実行時刻
- Outbox/DLQ滞留件数
- JWKSの本番応答
- 連携先別API scope/IP制限の実値
- 本番の `common_user_id` 件数、未解決、重複候補
- 本番の `customer_entitlements` 状態別件数

### 8.2 指示書と現行実装の主な差分

1. `service_code` 入力名が未対応
   - 現行は `system_key` / `service_key` 中心。

2. 一般ユーザーと代理店資格の分離が完全か追加確認が必要
   - `common_users` に代理店関連列が追加されているため、一般ユーザー情報と代理店状態の境界を確認する必要がある。

3. 販売事実Inboxの正式契約が不足
   - 現行は `customer_transactions` と `api/integrations/events` が近い役割を持つが、PR-A3の必須項目と完全一致しない。

4. 商品・報酬対象ルールが未完成
   - AIアート教室受講料金を `NOT_ELIGIBLE` に固定するマスター/ルールが必要。

5. 手動報酬CSV・支払申請が未完成
   - 報酬元帳、CSV検証、支払申請、承認、支払済み登録の一連機能が必要。

6. Feature Flagの粒度が不足
   - 指示書の8種フラグを同名・独立で確認できない。

### 8.3 重複・整理候補

- 共通ユーザー解決URLが `api/common-users/index.php` と `api/common-users/resolve/index.php` で互換維持されている。削除せず、ドキュメント上の正式URLを固定する必要がある。
- 外部連携設定が `admin/settings.php`、`admin/external_partners.php`、`admin/external_partner_wizard.php` に分散している。運用UIとしてはウィザード中心に整理し、詳細設定は上級者向けにする余地がある。
- CommonIdentity関連の管理画面が複数あるため、運用導線は今後整理対象。

## 9. PR分割案

| PR | 内容 | 影響範囲 | テスト方法 | ロールバック |
|---|---|---|---|---|
| PR-A1 | 一般ユーザー基盤の不足補完 | 共通ユーザー、サービス紐付け、管理画面 | 共通ID解決、競合、手動統合/分離、監査ログ | 追加画面・追加列を停止。既存APIは維持 |
| PR-A2 | 共通ユーザー解決API契約固定 | `/api/common-users/resolve`、入力正規化、レスポンス | 400/401/403/409/503、冪等性、既存互換 | v2追加の場合は旧APIへ戻す |
| PR-A3 | 販売事実Inbox | 新規Inbox、イベント受信、冪等性 | 重複、payload差分409、未解決common_user_id、順序逆転 | Feature Flag OFF、既存events API維持 |
| PR-A4 | 商品・報酬対象ルール | 商品マスター、報酬対象判定、UNKNOWN停止 | 未登録商品、AIアート教室NOT_ELIGIBLE、返金方針 | ルールFlag OFF、商品判定を停止 |
| PR-A5 | 手動報酬CSV・支払申請 | 報酬元帳、CSV取込、代理店表示、支払申請 | CSV検証、二重取込、承認/却下、支払済み | CSV機能Flag OFF、元帳は追記式で無効化 |
| PR-A6 | 購入者SSO・利用権実接続 | Shopping購入、利用権、SSO、返金取消 | 購入→権限→SSO→返金取消、応答喪失、再送 | SSO/利用権Flag OFF、既存手動運用へ戻す |
| PR-A7 | 5システム横断E2E | Passport、Shopping、Wallet、AIアート教室、Outbox | 指示書14シナリオをステージング実施 | 外部送信Flag OFF、連携先ごと停止 |

## 10. 次に必要な確認

実装に進む前に、以下を管理者またはサーバー側で確認する必要がある。

1. 本番・ステージングの `schema_migrations` 実適用状況
2. 本番・ステージングの Feature Flag 実値
3. `common_users` / `service_user_mappings` / `system_account_links` / `user_identities` の件数
4. `customer_entitlements` の状態別件数
5. Outbox / DLQ の滞留件数
6. cronの登録と直近実行時刻
7. JWKSエンドポイントの実応答
8. 最新mainのGitHub Actions結果

## 11. PR-A0結論

PR-A0のローカル事前確認では、基準コミット・バージョン・既存実装の主要位置は確認できた。

ただし、本番DB・ステージングDB・実サーバー設定の確認は未実施であるため、PR-A1以降に進む前に、未適用マイグレーション、Feature Flag、Outbox/DLQ、共通ID件数、利用権件数をサーバー上で確認する必要がある。

実装方針としては、既存の共通ID・紹介・Outbox・認証基盤を作り直さず、次の順番で不足分だけを追加するのが安全である。

1. `service_code` 互換追加と一般ユーザー分離の確認・補完
2. 共通ユーザー解決APIの契約固定
3. 販売事実Inboxの追加
4. 商品・報酬対象ルールの追加
5. 手動報酬CSV・支払申請
6. 購入者SSO・利用権の実接続確認
7. 5システム横断E2E
