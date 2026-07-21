# 共通顧客HUB Stage 0 現行差分レポート

作成日: 2026-07-20
対象: 戦国経済圏 代理店システム

## 目的

「千ノ国 代理店システム 共通顧客HUB改修指示書 v1」に対して、現行実装との差分、影響範囲、必要API、データ移行の有無を整理します。

この段階では、既存機能を変更しません。実装に入る前の確認資料として扱います。

## 現行で実装済みの主な要素

### DB

- `common_users`
- `service_user_mappings`
- `agency_customer_relations`
- `referral_tokens`
- `referral_sessions`
- `integration_event_logs`
- `external_partner_sites`
- `external_partner_event_logs`
- `sso_clients`
- `login_logs`

### API

- `/api/hierarchy.php`
- `/api/integrations/agencies`
- `/api/v2/user-mappings`
- `/api/v2/referral-relations`
- `/api/v2/referral-tokens`
- `/api/v2/referral-sessions`
- `/api/sso/jwks.php`

### 管理画面

- 共通ID管理
- 共通ID紐づけ管理
- 外部API連携
- SSO連携
- 連携ログ
- 操作ログ
- ログイン記録
- 代理店活動
- 管理スタッフ

## 指示書との差分

### 1. 共通顧客マスタ

現行の `common_users` は存在しますが、指示書が求める顧客HUBとしては項目が不足しています。

不足候補:

- 取得経路
- 取得元システム
- 登録時紹介者
- 現在担当代理店
- 代理店紐づけ状態
- 顧客管理状態
- 統合先 common_user_id
- 手動確認フラグ

影響:

- 代理店由来ユーザーと一般ユーザーの区別が弱い
- 外部システムから見た「この顧客は誰の顧客か」の判断が不十分

### 2. ID/認証情報の保持

指示書では `user_identities` を推奨していますが、現行では未実装です。

現行は `service_user_mappings` にメール、電話、ウォレットなどの一部情報を持たせています。

影響:

- LINE ID、メール、電話、ウォレット、外部認証IDなどを統一的に扱いにくい
- 同一人物判定の根拠を監査しにくい

### 3. システム別アカウント紐づけ

指示書では `system_account_links` を推奨しています。

現行では `service_user_mappings` が近い役割を持っています。

差分:

- テーブル名とフィールド構成が異なる
- `system_key` ではなく `service_key` を使っている
- 5システム共通の標準表現として再整理が必要

方針:

- 既存 `service_user_mappings` をすぐ削除しない
- 互換ビューまたは追加テーブルで `system_account_links` 相当を整える

### 4. 代理店との関係

現行の `agency_customer_relations` は存在します。

不足候補:

- 登録時紹介者と現在担当者の明確な分離
- 初回接点、最終接点、確定接点の履歴
- 代理店由来ではない一般ユーザーの明確な非表示制御

影響:

- ショッピングカートやパスポートから登録した一般ユーザーと、代理店紹介ユーザーの区別が運用上曖昧になる可能性がある

### 5. 接点履歴

指示書では `agent_touchpoints` が必要です。

現行では `referral_sessions`、`access_logs`、`leads` などに分散しています。

影響:

- どの代理店URLを踏んだか
- いつ紹介が確定したか
- 別代理店URLで上書きされなかったか

これらを一元的に追いにくい状態です。

### 6. アカウント統合ログ

指示書では `account_merge_logs` が必要です。

現行では共通IDの統合操作はありますが、専用の統合ログテーブルとしては不足しています。

影響:

- 後から「なぜ統合されたか」「誰が統合したか」を確認しにくい
- 誤統合時の復旧判断が難しくなる

### 7. 必須API

指示書のAPI名と現行APIに差があります。

未整備または別名のAPI:

- `POST /api/common-users/resolve`
- `POST /api/common-users`
- `POST /api/common-users/{common_user_id}/system-links`
- `GET /api/common-users/{common_user_id}`
- `POST /api/referrals/capture`
- `POST /api/referrals/confirm`
- `POST /api/common-users/{common_user_id}/assigned-agent`
- `GET /api/common-users/{common_user_id}/sales-context`

方針:

- 既存 `/api/v2/*` を維持する
- 新しい標準APIを追加し、必要に応じて内部で既存関数を利用する

### 8. Feature Flag

現行の feature flag は一部のみです。

追加候補:

- `common_hub_enabled`
- `common_hub_write_enabled`
- `common_hub_read_enabled`
- `referral_v2_enabled`
- `passport_integration_enabled`
- `ai_art_integration_enabled`
- `shopping_integration_enabled`
- `wallet_integration_enabled`

影響:

- 段階リリース、緊急停止、読み取りだけ有効化などの運用が弱い

## データ移行の有無

必要です。ただし、一括自動統合は行いません。

### 必要な移行

- 既存代理店を common_user_id に紐づける
- 既存の `service_user_mappings` を標準リンクとして扱えるようにする
- 既存紹介URL、問い合わせ、アクセスログを共通IDと接続する
- 既存外部システムIDをリンク候補として整理する

### 自動統合してよい候補

- 同一 LINE user ID
- 明示的な外部システムID
- 検証済みで一意なメールアドレス

### 自動統合しない候補

- 名前だけ一致
- 未検証メール
- 家族共有の電話番号
- 紹介者が競合しているもの
- ウォレット残高や権利が絡むもの

## 必要な追加実装

優先順:

1. feature flag 追加
2. `user_identities` 追加
3. `system_account_links` 相当の標準化
4. `agent_touchpoints` 追加
5. `account_merge_logs` 追加
6. `common-users/resolve` API
7. `system-links` API
8. `referrals/capture` / `referrals/confirm` API
9. 共通顧客詳細画面
10. dry-run 移行レポート

## React 化との関係

React 化はこの後です。

先に共通顧客HUB APIを安定させることで、将来の React 管理画面は PHP の画面ロジックに依存せず、API だけで動かせます。

React 化の優先候補:

- 共通顧客検索
- 共通顧客詳細
- 代理店活動分析
- 外部連携ログ
- SSO/外部API設定

## リスク

- DB変更時の既存ページ 500 エラー
- 同一人物判定の誤統合
- 代理店紹介者の上書き
- 外部システムごとのID仕様差
- APIキー運用の混乱
- React 化を急ぎすぎることによる既存運用の停止

## 推奨する次フェーズ

次は Stage 1 として、DBを破壊しない追加型マイグレーションを作成します。

最初に追加する候補:

- `user_identities`
- `system_account_links`
- `agent_touchpoints`
- `account_merge_logs`
- feature flag 用 `system_settings`

既存テーブルを削除・改名せず、互換性を保ったまま追加します。
