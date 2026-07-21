# Stage 4：Transactional Outbox・認証強制・自動テスト実装指示書

## 1. 文書の目的

本書は、千ノ国 代理店システムの外部連携基盤を、本番接続に耐えられる状態へ仕上げるための実装指示書です。

現在のバージョン `3.6.80` では、共通顧客ID、代理店公開ID、紹介トークン、販売・クロージング担当、HMAC署名、Outbox、再送、DLQ、受入テスト文書まで実装されています。

ただし、現在のOutboxは外部HTTP送信後に送信結果を記録する方式であり、厳密なTransactional Outboxにはなっていません。また、APIスコープの未設定時許可、旧APIの独自認証、代理店ID検索の曖昧性、Outboxの同時実行排他不足、自動テスト未整備が残っています。

本Stageでは、次を完成させます。

1. 業務DB更新とOutbox登録の原子性確保
2. Outbox workerの排他制御
3. API認証・スコープのfail-closed化
4. `agency_id` と `external_id` の検索分離
5. cron認証の安全化
6. HMACの本番必須化
7. GitHub Actionsによる自動テスト
8. 本番移行・ロールバック手順の確立

---

## 2. 対象リポジトリ

| 項目 | 内容 |
|---|---|
| リポジトリ | `team478a/sengoku-agency-system` |
| 対象ブランチ | `main` |
| 開始基準コミット | `782c983b976f9fc5c6997f7a811075b28d8518a3` |
| 開始時バージョン | `3.6.80` |
| 想定作業ブランチ | `feature/v3.6.81-transactional-outbox` |
| 想定次バージョン | `3.6.81` |

---

## 3. 現在の実装状況

### 3.1 実装済み

- `common_user_id` の発行・解決・統合
- `agency_id = agents.agent_code` の外部契約
- `referral_token` の正式トークン化
- 旧紹介コード、`ref`、`invite_token` のエイリアス解決
- `registration_referrer_agency_id`
- `assigned_agency_id`
- `sales_agent_id`
- `closing_agent_id`
- `customer_transactions`
- `customer_assignment_histories`
- APIキーの有効期限・IP許可リスト用カラム
- APIスコープ用カラム
- HMAC署名ヘッダー
- `integration_outbox_events`
- `integration_event_attempts`
- 指数バックオフ再送
- 最大試行回数到達後のDLQ移行
- Outbox管理画面
- Outbox再送cron
- 外部連携契約書
- 手動受入テスト表

### 3.2 未完成または要修正

- 業務更新とOutbox登録が同一トランザクションではない
- 通常処理で外部HTTP送信を先に実行している
- Outbox取得時の排他制御がない
- `processing` 状態、claim token、worker lockがない
- cron URLのクエリパラメータでトークンを受け付ける
- Outbox管理操作がスーパー管理者限定ではない
- APIスコープが未設定の場合に許可される
- 階層APIが独自認証で、有効期限・IP・スコープを強制していない
- 代理店同期APIが `external_id OR agent_code` で検索する
- HMAC検証が外部システムの必須条件ではない
- GitHub Actionsと自動テストがない

---

## 4. 変更範囲

主な変更対象は以下です。

```text
includes/functions.php
cron/external_integration_retry.php
admin/integration_outbox.php
admin/operations.php
admin/external_partners.php
api/v2/bootstrap.php
api/hierarchy.php
api/integrations/agencies/index.php
api/common-users/index.php
api/referrals/index.php
api/v2/user-mappings/index.php
api/v2/referral-relations/
紹介トークン検証API
紹介セッションAPI
config/migrations/3.6.81.sql
docs/integration/
tests/
.github/workflows/ci.yml
VERSION
CHANGELOG.md
```

必要に応じて、新規ファイルを追加して構いません。

推奨新規ファイル例：

```text
cron/process_integration_outbox.php
tests/bootstrap.php
tests/integration/CommonUserApiTest.php
tests/integration/ReferralApiTest.php
tests/integration/OutboxTest.php
tests/integration/ApiScopeTest.php
tests/unit/HmacSignatureTest.php
```

---

## 5. 変更禁止範囲

以下の既存仕様・データを壊してはいけません。

### 5.1 ID

- `common_user_id` の形式
- `agency_id = agents.agent_code`
- 既存の `agents.agent_code`
- `agents.id` の内部参照
- `agents.parent_id` による代理店階層
- `system_key + external_user_id`
- 旧 `service_key + service_user_id`

### 5.2 紹介・担当

- `referral_token`
- `referral_session_key`
- `agency_customer_relations.locked`
- `registration_referrer_agency_id`
- `assigned_agency_id`
- `sales_agent_id`
- `closing_agent_id`
- 既に確定済みの紹介代理店

### 5.3 認証・SSO

- 管理者ログイン
- 代理店ログイン
- 初回パスワード設定
- RS256署名
- JWKS公開
- `sub = agent_code`
- 既存SSOクライアント

### 5.4 運用

- 既存の外部連携ログ
- 旧失敗ログ再送機能
- DBマイグレーション履歴
- 更新前バックアップ
- `config/database.php`
- `config/installed.lock`
- `uploads/`
- `backups/`

旧機能を廃止する場合も、即時削除せずFeature Flagまたは互換期間を設けてください。

---

## 6. 必須実装

# 6.1 Transactional Outbox化

## 現在の問題

現在の外部イベント送信は概ね次の順序です。

```text
外部HTTP送信
↓
送信結果取得
↓
Outboxへ記録
↓
試行履歴へ記録
```

この方式では、次の事故が起こり得ます。

- 業務DB更新後、Outbox登録前にプロセス停止してイベント消失
- 外部送信成功後、Outbox登録前に停止して送信履歴消失
- 外部サービス停止により業務APIの応答が遅延
- 業務DBロールバック後に外部イベントだけ届く

## 必須変更

処理を次へ分離してください。

```text
enqueueExternalPartnerEvent()
claimDueIntegrationOutboxEvents()
deliverIntegrationOutboxEvent()
processIntegrationOutboxBatch()
```

業務処理中は外部HTTP送信を行わず、Outboxへ `pending` 行を登録するだけにします。

```text
業務データ更新
＋
Outbox pending登録
↓
同一DBトランザクションでcommit
↓
workerがOutboxを取得
↓
外部HTTP送信
```

## 対象イベント

少なくとも以下をOutbox経由へ変更してください。

- 代理店作成
- 代理店更新
- 代理店停止
- 問い合わせ作成
- 共通顧客作成
- 共通顧客更新
- 共通ID統合
- 紹介確定
- 顧客担当変更
- 販売担当変更
- クロージング担当変更
- 権利付与・取消イベントを追加する場合

## 受入条件

- 業務データとOutbox行が同一トランザクションで保存される
- Outbox登録失敗時は業務データもロールバックされる
- 外部システム停止中でも業務APIはcommitできる
- 業務commit後にPHPプロセスを停止してもOutboxイベントが残る
- 通常APIが外部HTTP応答を待たない

---

# 6.2 Outbox排他制御

## 現在の問題

現在は `pending` / `failed` 行を通常のSELECTで取得しており、以下の同時実行で重複送信する可能性があります。

- cronの二重起動
- cron実行中の管理画面手動再送
- 複数Webサーバーからのcron実行
- 前回処理のタイムアウト中に次回処理開始

## DB追加項目

`integration_outbox_events` に以下を追加してください。

```sql
ALTER TABLE integration_outbox_events
    ADD COLUMN IF NOT EXISTS locked_at DATETIME DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS locked_by VARCHAR(100) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS claim_token VARCHAR(100) DEFAULT NULL;
```

状態へ以下を追加します。

```text
processing
```

## claim処理

MySQL 8以上が保証される場合：

```sql
SELECT ...
FROM integration_outbox_events
WHERE status IN ('pending', 'failed')
  AND (next_attempt_at IS NULL OR next_attempt_at <= NOW())
FOR UPDATE SKIP LOCKED
LIMIT ?;
```

MySQLバージョンが保証されない場合は、`claim_token` を使った原子的UPDATE方式を採用してください。

## stale lock

以下の設定を追加してください。

```text
external_partner_outbox_lock_timeout_minutes=15
```

一定時間を超えた `processing` は、worker停止とみなして再取得可能にします。

## 受入条件

- 2つのworkerを同時実行しても同一イベント送信は1回
- cronと管理画面再送を同時実行しても重複しない
- 処理中イベントは別workerが取得しない
- stale lockを回収できる
- 成功イベントは再取得されない

---

# 6.3 Outbox設定値の実利用

既存設定：

```text
external_partner_outbox_retry_enabled
external_partner_outbox_default_max_attempts
```

現状は設定値が処理へ十分反映されていません。

## 必須変更

- `external_partner_outbox_retry_enabled=0` の場合、自動再送を停止
- 管理画面の一括再送は、無効状態を表示してスーパー管理者だけ手動実行可能にするか、完全停止する
- 新規Outbox行の `max_attempts` は `external_partner_outbox_default_max_attempts` を使用
- 既存Outbox行の `max_attempts` は原則変更しない
- 管理画面へ現在の設定値を表示

## 受入条件

- 再送無効時にcronが外部送信しない
- 最大試行回数設定が新規行へ反映される
- 最大回数到達後にDLQへ移る

---

# 6.4 cron認証の安全化

## 現在の問題

現在はURLクエリの `token` を受け付けます。

クエリトークンは以下へ残る可能性があります。

- Webサーバーアクセスログ
- CDNログ
- APM
- ブラウザ履歴
- cron管理画面
- エラーログ

## 必須変更

URLクエリの `token` を廃止します。

HTTP経由の場合：

```http
POST /cron/external_integration_retry.php
Authorization: Bearer {CRON_TOKEN}
```

可能であればCLI専用処理を追加してください。

```bash
php cron/process_integration_outbox.php --limit=50
```

## 受入条件

- GETクエリのtokenは拒否される
- GETによる再送処理は実行されない
- Bearer tokenまたはCLIだけで実行可能
- cron tokenがログへ出ない

---

# 6.5 Outbox管理権限

## 現在の問題

`admin/integration_outbox.php` は通常管理者ログインだけでアクセス可能で、staff管理者も再送・DLQ操作できる可能性があります。

## 必須変更

最低限、書き込み操作はスーパー管理者限定にしてください。

```php
requireSuperAdmin();
```

閲覧をstaffへ許可する場合は、閲覧と操作を分離してください。

将来の権限候補：

```text
integration_outbox_view
integration_outbox_retry
integration_outbox_dlq_manage
```

## 受入条件

- staff管理者は再送できない
- staff管理者はDLQ移動・解除できない
- スーパー管理者はCSRF検証後に操作可能
- 全操作を監査ログへ記録する

監査ログへ保存する内容：

- 管理者ID
- 操作種別
- Outbox ID
- event_id
- 変更前状態
- 変更後状態
- 理由
- IPハッシュ
- 実行日時

---

# 6.6 APIスコープのfail-closed化

## 現在の問題

現在のスコープ確認は、以下の場合に処理を許可します。

- `inbound_scopes` カラムがない
- `inbound_scopes` が空
- legacy API token

## 段階移行設定

以下を追加してください。

```text
integration_scope_enforcement_enabled=0
legacy_external_api_token_enabled=1
```

本番移行手順：

1. 全接続先へスコープを設定
2. ステージング接続テスト
3. `integration_scope_enforcement_enabled=1`
4. 未設定スコープを403にする
5. legacy token利用状況を確認
6. `legacy_external_api_token_enabled=0`

## エラーコード

```text
SCOPE_NOT_CONFIGURED
SCOPE_FORBIDDEN
LEGACY_API_KEY_DISABLED
```

## 必須スコープ

```text
common_users:read
common_users:write
referrals:write
agencies:read
agencies:write
agencies:contact:read
user_mappings:read
user_mappings:write
```

## 受入条件

- 強制モードでスコープ空欄は403
- readキーでwrite処理は403
- 期限切れAPIキーは401
- 許可外IPは403
- legacy token無効時は旧キーを拒否

---

# 6.7 外部API認証の統一

以下のAPIを共通認証基盤へ移行してください。

```text
/api/hierarchy.php
/api/integrations/agencies
/api/common-users/*
/api/referrals/*
/api/v2/user-mappings/*
/api/v2/referral-relations/*
紹介トークン検証API
紹介セッションAPI
```

共通処理：

```text
apiV2Authenticate()
apiV2RequireScope()
APIキー有効期限確認
IP許可リスト確認
接続先status確認
inbound_allowed_system_key確認
```

階層APIのスコープ：

```text
agencies:read
```

`include_contact=1` の場合：

```text
agencies:contact:read
```

APIキーをGETクエリ `token` から取得する方式は廃止してください。

---

# 6.8 `agency_id` と `external_id` の検索分離

## 現在の問題

現在の代理店同期APIは次のOR検索を行います。

```sql
WHERE agents.external_id = ?
   OR agents.agent_code = ?
LIMIT 1
```

次のデータが存在すると、誤った代理店を取得する可能性があります。

```text
代理店A.agent_code  = ABC001
代理店B.external_id = ABC001
```

## 必須変更

入力項目ごとに検索先を分離してください。

```text
agency_id   → agents.agent_code
external_id → agents.external_id
```

関数例：

```text
findAgentByAgencyId()
findAgentByExternalId()
```

両方指定された場合：

- 同一レコードなら処理継続
- 異なるレコードなら409

エラーコード：

```text
AGENCY_ID_CONFLICT
```

## 受入条件

- `agency_id` は `agent_code` だけを検索
- `external_id` は `external_id` だけを検索
- OR検索を使用しない
- ID競合時はDB更新しない

---

# 6.9 HMACの本番必須化

## 現在の仕様

```text
X-SenNoKuni-Key-Id
X-SenNoKuni-Timestamp
X-SenNoKuni-Nonce
X-SenNoKuni-Signature
```

署名対象：

```text
timestamp + "\n" + nonce + "\n" + raw_json_body
```

アルゴリズム：

```text
HMAC-SHA256
```

## 必須変更

以下を追加してください。

```text
external_partner_hmac_required_in_production=1
```

本番モードで以下の場合は送信しません。

- `hmac_secret` が空
- `hmac_key_id` が空
- HMACが無効

エラーをOutboxへ記録し、DLQまたは設定エラー状態として運用画面へ表示してください。

## 外部システム受入条件

- 署名一致
- timestampが許容時間内
- nonce再利用拒否
- raw bodyで検証
- key IDによるsecretローテーション

---

# 6.10 自動テスト・GitHub Actions

## 必須ファイル

```text
.github/workflows/ci.yml
```

## 最低限のCI

### PHP構文

```bash
find . -name '*.php' -not -path './vendor/*' -print0 | xargs -0 -n1 php -l
```

### DBマイグレーション

- 空DBへ全マイグレーション適用
- 既存3.6.80相当DBへ3.6.81適用
- マイグレーション再実行の安全性

### APIテスト

- APIキーなし
- 不正APIキー
- 期限切れAPIキー
- 許可外IP
- スコープ不足
- スコープ未設定
- legacy token無効

### 共通ID

- 新規発行
- 外部ユーザーID再送
- 未検証メール非統合
- 検証済みメール統合
- 未検証電話非統合
- 検証済みLINE ID統合

### 紹介

- capture
- confirm
- 旧紹介コード解決
- 取引の冪等性
- 4代理店ロール保存
- `locked=1` の紹介代理店保護

### Outbox

- 業務更新とOutbox登録の原子性
- 外部停止中も業務commit
- 2 worker同時実行
- cronと手動再送の同時実行
- 指数バックオフ
- 最大回数到達後DLQ
- stale lock回収
- 成功後の再取得防止

### HMAC

- 正常署名
- 本文改ざん
- timestamp期限切れ
- nonce再利用
- key rotation

## Branch Protection

可能であれば、mainへのマージ条件としてCI成功を必須化してください。

---

## 7. 必須接続テスト

対象システム：

- 千ノ国パスポート
- 千ノ国ウォレット
- ショッピング／マーケット
- AIアート教室

各システムで以下を実施してください。

### 7.1 共通ID

- 新規登録で `common_user_id` 発行
- 再ログインで同一ID取得
- 未検証情報で誤統合しない
- 外部システム側へ `common_user_id` 保存

### 7.2 代理店

- `agency_id` 取得
- 親代理店取得
- 内部 `agents.id` を外部永続キーにしない
- `agency_id` と `external_id` の競合試験

### 7.3 紹介

- `referral_token` capture
- `referral_session_key` 継承
- confirm
- 紹介代理店固定
- 同一注文再送時の重複防止

### 7.4 担当者

- `registration_referrer_agency_id`
- `assigned_agency_id`
- `sales_agent_id`
- `closing_agent_id`

### 7.5 Webhook

- HMAC正常受信
- 改ざん拒否
- nonce再利用拒否
- 外部停止時のOutbox蓄積
- 復旧後の再送
- 同一 `event_id` の重複処理拒否

---

## 8. 受入条件

以下をすべて満たすことを完了条件とします。

### 8.1 データ整合性

- 業務更新とOutbox登録が同一トランザクション
- Outbox登録失敗時に業務更新もロールバック
- 紹介代理店固定を維持
- `common_user_id` の重複発行なし
- 4代理店ロールを別々に保存

### 8.2 Outbox

- 外部HTTP送信はworkerだけが実行
- 二重workerでも重複送信なし
- stale lock回収可能
- 指数バックオフ
- DLQ移行
- DLQ手動復旧
- 全操作監査ログ

### 8.3 認証

- スコープ強制モードで未設定を拒否
- APIキー期限切れを拒否
- 許可外IPを拒否
- GETクエリのAPIキー・cron tokenを拒否
- staff管理者のOutbox操作を拒否

### 8.4 ID

- `agency_id` と `external_id` を別検索
- 競合時409
- `agency_id = agent_code` を維持
- `agents.id` は外部永続キーに使用しない

### 8.5 テスト

- 全PHP構文チェック成功
- DBマイグレーション成功
- API自動テスト成功
- Outbox並行実行テスト成功
- HMACテスト成功
- GitHub Actions成功

---

## 9. 本番適用手順

1. 本番DBとファイルのバックアップ
2. ステージングへ3.6.81適用
3. マイグレーション確認
4. 自動テスト実行
5. 各接続先にAPIスコープ設定
6. 各接続先にHMAC secret設定
7. cron tokenをBearer方式へ更新
8. Outbox workerを単一インスタンスで開始
9. 接続先ごとにcapture / confirm / Webhook試験
10. `integration_scope_enforcement_enabled=1`
11. `legacy_external_api_token_enabled=0`
12. Outboxを監視しながら本番開始

段階的に有効化してください。

```text
AIアート教室
↓
パスポート
↓
ウォレット
↓
ショッピング／マーケット
```

決済・権利付与を含むショッピング連携は最後に行ってください。

---

## 10. ロールバック条件

以下の場合は本番有効化を停止してください。

- 同一Outboxイベントが重複送信される
- 業務データだけ保存されOutboxが作成されない
- 正常なAPIキーが一斉に401／403になる
- 紹介代理店が意図せず上書きされる
- `common_user_id` が重複発行される
- 既存代理店ログイン・SSOが失敗する
- 大量のDLQが発生する
- 外部システムで同一注文が重複処理される
- HMAC検証不能で全Webhookが停止する

---

## 11. ロールバック手順

1. Outbox workerとcronを停止
2. `integration_scope_enforcement_enabled=0`
3. `external_partner_hmac_required_in_production=0`
4. 必要な場合のみlegacy API tokenを一時復旧
5. 新規イベント送信Feature Flagを停止
6. OutboxイベントとDLQは削除せず保持
7. DB追加カラム・追加テーブルは即時DROPしない
8. 原因調査後に対象イベントだけ再送
9. `VERSION` と適用済みマイグレーションを記録

ロールバック時も、以下を破棄しないでください。

- `integration_outbox_events`
- `integration_event_attempts`
- `integration_event_logs`
- `customer_transactions`
- `customer_assignment_histories`
- `account_merge_logs`

---

## 12. 完了時の提出物

以下をリポジトリへ追加・更新してください。

```text
IMPLEMENTATION_STATUS.md
IMPLEMENTATION_HISTORY.md
TEST_RESULTS.md
OUTBOX_MIGRATION_GUIDE.md
EXTERNAL_API_COMPATIBILITY.md
```

各文書へ記載する内容：

- 対象コミット
- 変更ファイル一覧
- 適用マイグレーション
- 新規設定値
- API互換性
- 自動テスト結果
- 手動接続テスト結果
- 未解決事項
- 本番適用手順
- ロールバック手順

---

## 13. 完了報告時に必要な情報

開発完了時は、次の形式で報告してください。

```text
1. 実装した項目
2. 実装しなかった項目と理由
3. 変更ファイル
4. DBマイグレーション
5. 新規設定値
6. API変更点
7. 既存互換性への影響
8. 自動テスト結果
9. 手動テスト結果
10. 本番適用時の注意事項
11. ロールバック条件
12. 残課題
```

テスト未実施の項目を「完了」としないでください。ソースコード上で実装済みの事項、設定が必要な事項、実環境テストが必要な事項を分けて報告してください。
