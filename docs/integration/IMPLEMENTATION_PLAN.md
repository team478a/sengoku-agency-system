# 実装計画

## 前提

代理店システムを、将来の共通ID・紹介関係・外部サービス連携の中心ハブとして拡張します。

対象になる登録入口:

- 代理店システム
- ショッピングカート
- 戦国パスポート
- ウォレット
- 将来追加される外部サービス

## 実装方針

- 既存機能は壊さない
- 既存APIは互換維持
- 新機能は `/api/v2/*` に追加
- DB変更は追加方式
- feature flag 初期OFF
- 外部サービス起点の登録も必ず取り込める構成にする

## Phase 0: 準備

実装前の調査・設計整理。

成果物:

- `CURRENT_ARCHITECTURE.md`
- `CURRENT_API_INVENTORY.md`
- `CURRENT_DB_SCHEMA.md`
- `RISK_ASSESSMENT.md`
- `IMPLEMENTATION_PLAN.md`

## Phase 1: 共通ID基盤

追加するもの:

- `service_user_mappings`
- `agency_customer_relations`
- `integration_idempotency_keys`
- `integration_event_logs`
- feature flag
  - `common_id_enabled`
  - `referral_v2_enabled`
  - `external_registration_capture_enabled`

この段階では、APIは管理者・開発者向けの最小実装に留めます。

## Phase 2: 外部サービス起点登録API

目的:

ショッピングカートや戦国パスポートで先に登録されたユーザーを代理店システムへ取り込む。

追加API:

```text
POST /api/v2/user-mappings
POST /api/v2/referral-relations
GET  /api/v2/referral-relations/by-common-user/{common_user_id}
```

受け取る情報:

- `common_user_id`
- `service_key`
- `service_user_id`
- `email`
- `phone`
- `wallet_address`
- `referral_token`
- `agent_code`
- `project_slug`

## Phase 3: 紹介トークン

目的:

LP、招待URL、外部サービス遷移時に紹介元を失わないようにする。

追加するもの:

- `referral_tokens`
- `referral_sessions`

追加API:

```text
GET /api/v2/referral-tokens/{token}/validate
POST /api/v2/referral-sessions
```

実装バージョン:

```text
3.6.61
```

## Phase 4: 既存LP・問い合わせとの接続

目的:

既存LP経由の問い合わせやクリックも、可能な範囲で共通ID・紹介関係に接続する。

対応:

- LP URLに紹介トークンを付与
- `contact.php` 保存時に対応カラムがあれば保存
- 既存 `leads.agent_id` は維持

## Phase 5: 外部サービスへの通知

目的:

代理店システム側で承認・登録・停止・削除が発生した場合に、登録済み外部サイトへ通知する。

既存の `external_partner_sites` を利用し、連携先ごとに送信します。

## Phase 6: 売上連携

目的:

カート購入・継続課金・返金などを代理店階層に紐づける。

追加候補:

- `sale_events`
- `sale_hierarchy_members`

初期フェーズでは未実装でもよいですが、設計上は拡張可能にしておきます。

## 推奨する最初の実装

最初に実装するべき範囲は Phase 1 です。

理由:

- 既存機能への影響が少ない
- 後続APIの土台になる
- 外部サービス側の開発者と項目定義を合わせやすい
- feature flag OFFで安全にデプロイできる

## 最初のZIP更新候補

バージョン例:

```text
3.6.59 common ID foundation
```

内容:

- 追加マイグレーション
- feature flag
- 共通ID用ヘルパー
- 連携ログ保存ヘルパー
- 最小限の管理者向け確認画面

この段階では、既存LP・問い合わせ・代理店同期の挙動は変更しません。
