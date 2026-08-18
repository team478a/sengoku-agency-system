# 外部連携シンプル化 PR-S01 差分確認

作成日: 2026-08-03  
対象バージョン: 3.6.130  
対象ソース: `current_source_3_6_124_20260803`

この文書は、`SENGOKU_AGENCY_EXTERNAL_INTEGRATION_SIMPLIFICATION_IMPLEMENTATION_INSTRUCTIONS.md` に対する現行実装との差分を整理したものです。PR-S01ではドキュメント契約の固定までを対象とし、API・DB・UIの変更は次フェーズで行います。

## 1. 確認した現行実装

| 項目 | 現状 | 根拠 |
| --- | --- | --- |
| 現行バージョン | `3.6.130` | `VERSION` |
| 代理店送信先生成 | `/api/integrations/agencies` に固定 | `includes/functions.php` の `buildExternalPartnerEndpoint()` |
| 外部イベント送信 | 共通イベントも同じ送信先へ送信 | `includes/functions.php` の `dispatchExternalPartnerEvent()` |
| 共通イベント送信関数 | 実装あり | `syncCommonUserHubEventToExternalPartners()` |
| HMAC署名 | 送信側の任意機能として実装あり | `buildExternalPartnerHmacHeaders()` |
| outbox | 実装あり | `integration_outbox_events` 系マイグレーション |
| `/api/integrations/events` | 未実装 | `rg` 検索で該当なし |
| API v2 resolve | 現行正式導線ではない | 既存ドキュメントとAPIラッパー確認 |
| 管理画面 | 連携先ごとの単一フォーム中心 | `admin/external_partners.php` |

## 2. 指示書との差分

### D1. 代理店同期APIと共通イベントAPIが分かれていない

現状は、代理店同期も共通顧客イベントも `/api/integrations/agencies` 系の送信先へ流れます。

必要な変更:

- `agency_sync_endpoint` を代理店マスタ専用にする
- `common_event_endpoint` を共通イベント専用にする
- 送信イベント種別によってエンドポイントを切り替える

影響範囲:

- `includes/functions.php`
- `admin/external_partners.php`
- `config/migrations`
- 外部開発者向けドキュメント

DB変更:

- `external_partner_sites` にエンドポイント分離用カラム追加が必要

### D2. `/api/integrations/events` が未実装

外部システムから代理店システムへ、注文・決済・権利付与などを通知する汎用イベント受信APIがありません。

必要な変更:

- `api/integrations/events` を追加
- APIキー認証
- idempotency対応
- event名の検証
- common_user/referral/agent/project/productへの正規化

影響範囲:

- `api/integrations`
- `includes/functions.php`
- `config/migrations`
- テスト用fixture

DB変更:

- イベント受信ログまたは既存テーブルへの保存設計が必要

### D3. 外部システムごとの設定項目がまだ混在している

現状UIは、外部連携先を登録できますが、利用者には「AI側が発行するキー」と「外部側が発行するキー」の役割が分かりにくい状態です。

必要な変更:

- 受信用APIキー: 外部システムが代理店システムへ送る時に使う
- 送信用APIキー: 代理店システムが外部システムへ送る時に使う
- SSO: 代理店・スタッフログイン用
- common event endpoint: 共通イベント送信用
- agency sync endpoint: 代理店マスタ送信用

影響範囲:

- `admin/external_partners.php`
- ヘルプ文言
- 外部開発者向け手順書

### D4. `project_key` と `project_slug` の用語揺れ

現行ドキュメントには `project_slug` と `project_key` が混在しています。

必要な変更:

- 新規正式名は `project_key`
- 既存互換として `project_slug` を受ける
- レスポンスでは当面両方返す、または正式名を明記する

影響範囲:

- ドキュメント
- `/api/hierarchy.php`
- `referrals/capture`
- `referrals/confirm`
- 新設予定の `/api/integrations/events`

### D5. SSOと共通ID連携の責任範囲が混ざりやすい

SSOは代理店・スタッフの外部ポータルログイン用です。一般購入者・一般会員の統合は `common_user_id` で扱うべきです。

必要な変更:

- ドキュメント上でSSO対象を明確化
- 外部開発者向け画面・手順でも「SSO」と「共通顧客ID」を分けて説明

影響範囲:

- `docs/integration`
- `admin/sso_settings.php`
- `admin/external_partners.php`

## 3. PR-S01で追加した契約

追加文書:

- `docs/integration/EXTERNAL_INTEGRATION_CONTRACT_V2.md`

この文書で固定したこと:

- 代理店システムと外部システムの責任分界
- `system_key`、`site_key`、`project_key`、`product_code` の使い分け
- `/api/integrations/agencies` と `/api/integrations/events` の役割分離
- SSOは代理店・スタッフ向けであり、一般顧客IDとは分けること
- 現行APIの後方互換方針

## 4. 次フェーズ候補

### PR-S02: `/api/integrations/events` の追加

内容:

- 汎用イベント受信API追加
- APIキー認証
- idempotency対応
- イベントログ保存
- `order.completed`、`payment.succeeded`、`entitlement.granted` などを受ける土台を作る

### PR-S03: 送信先分離

内容:

- `external_partner_sites` に `agency_sync_endpoint` と `common_event_endpoint` を追加
- 既存 `base_url` からの自動補完
- agency系イベントとcommon系イベントの送信先を分ける

### PR-S04: 管理画面整理

内容:

- 連携先ごとにキーを見やすく分ける
- 受信用APIキーと送信用APIキーを分離表示
- 接続テストを agency sync / common event / SSO に分ける

### PR-S05: セットアップウィザード

内容:

- 外部サービス追加時のステップ表示
- 最初に何を設定するかを画面上で案内
- 接続テスト結果を記録

### PR-S06: 外部開発者向けドキュメントの最新版統一

内容:

- 契約v2をもとに既存ドキュメントを整理
- endpoint・認証・用語の不一致を解消
- ダウンロード可能なMDファイルとして管理画面から取得可能にする

## 5. 今回未実施のこと

PR-S01の範囲外として、以下はまだ実装していません。

- DBマイグレーション追加
- `/api/integrations/events` 追加
- UI変更
- Webhook送信先分離
- 接続テスト追加
- 既存ドキュメントの全面差し替え

