# 共通ユーザー基盤分離 実装前確認レポート

作成日: 2026-08-17  
対象指示書: AGENCY_SYSTEM_COMMON_IDENTITY_SEPARATION_INSTRUCTIONS_20260816.md  
確認対象ソース: current_source_3_6_124_20260803

## 結論

実装は可能です。

ただし、今回の指示は既存の共通ID、紹介、代理店階層、外部連携APIに影響するため、いきなり本体を大きく改修するより、まずは PR-B1 相当の「現状調査・契約テスト追加」から進めるのが安全です。

現行ソースには共通ユーザー基盤の土台はありますが、指示書が求める「共通ユーザー基盤を代理店ドメインから独立させる」構造にはまだなっていません。

## 現在すでに存在するもの

### DB

以下のテーブル・カラムは現行ソースに存在します。

- `common_users`
- `user_identities`
- `system_account_links`
- `agency_customer_relations`
- `common_hub_enabled`
- `common_hub_read_enabled`
- `common_hub_write_enabled`

関連マイグレーション:

- `config/migrations/3.6.59.sql`
- `config/migrations/3.6.60.sql`
- `config/migrations/3.6.72.sql`
- `config/migrations/3.6.78.sql`

### API

以下のAPIは現行ソースに存在します。

- `POST /api/common-users/resolve`
- `POST /api/common-users/{common_user_id}/system-links`
- `GET /api/common-users`
- `POST /api/referrals/capture`
- `POST /api/referrals/confirm`
- `/api/v2/user-mappings`
- `/api/v2/referral-sessions`
- `/api/v2/referral-relations`
- `/api/integrations/events`

### 共通ID関連ソース

現行では以下のみが `src/CommonIdentity` にあります。

- `src/CommonIdentity/CommonUserInput.php`
- `src/CommonIdentity/CommonUserInputNormalizer.php`

## 指示書との差分

### 1. 共通ユーザー基盤の層分離が未完了

指示書では、以下のような責務分離が想定されています。

- CommonIdentity Application
- CommonIdentity Domain
- CommonIdentity Infrastructure
- AgencyLinking / AgencyAssignment

現行では、`api/common-users/index.php` に解決、作成、照合、代理店項目更新、ログ記録がまとまっており、APIファイルが薄い入口になっていません。

### 2. common_users に代理店系フィールドが残っている

現行の `commonUsersApiResolve()` は `updateCommonUserHubFields()` を通じて、以下を `common_users` 側へ更新しています。

- `registration_referrer_agent_id`
- `assigned_agent_id`
- `agent_link_status`

互換性のため現時点で即削除はできませんが、指示書の方針では、将来的に代理店との関係は `agency_customer_relations` などの代理店側コンテキストへ寄せる必要があります。

### 3. 一般ユーザー対応の契約テストが不足

現行テスト `tests/Characterization/CommonIdentityFoundationTest.php` は、主に入力正規化と照合候補のテストです。

指示書が求める以下の確認は、まだテストとして固定されていません。

- 代理店でない一般ユーザーでも `common_user_id` を発行できる
- 外部サービスユーザーを `system_account_links` に安全に紐づけられる
- 代理店関係がないユーザーでも resolve が失敗しない
- 紹介関係がある場合だけ代理店関係を別管理する
- 既存APIレスポンス互換を壊さない

### 4. API互換を守るための安全網がまだ弱い

以下は既存外部システムが使っている可能性が高いため、変更前に契約テストで固定する必要があります。

- `/api/common-users/resolve`
- `/api/v2/common-users/resolve`
- `/api/referrals/capture`
- `/api/referrals/confirm`
- `/api/integrations/agencies`
- `/api/hierarchy.php`

### 5. 本番DBとの差分確認は未実施

ソース上はマイグレーションが存在しますが、本番DBで全テーブル・カラムが確実に存在するかは未確認です。

管理画面上ではマイグレーション適用済みに見えるケースでも、過去にアップデート順序やSQL失敗が発生しているため、実装前に読み取り専用の確認が必要です。

### 6. Git管理状態に注意

親フォルダには `.git` が見えますが、通常の作業ツリーとして認識されない状態でした。

今後GitHubへ反映する場合は、作業対象フォルダを明確にしてから差分管理する必要があります。

## 影響範囲

### 影響が大きい機能

- 外部システムからの共通ID解決
- 紹介URL流入
- 購入・予約・申込時の代理店紐づけ
- 外部Webhook通知
- SSO連携
- 代理店階層API
- 共通顧客HUB管理画面

### 触ってはいけない前提

以下は今回の分離作業でも互換維持が必要です。

- 既存URL
- 既存API認証
- 既存レスポンスの主要フィールド
- 既存代理店階層
- 既存LP URL
- 既存紹介URL
- 既存外部連携先の登録情報

## 推奨する次工程

### PR-B1: 現状調査・契約テスト

本体改修前に、以下を追加します。

- 共通ID resolve の契約テスト
- 一般ユーザー resolve の契約テスト
- system_account_links の契約テスト
- 代理店関係がないユーザーの契約テスト
- 既存API互換の固定テスト

この段階ではDB変更・API変更は行いません。

### PR-B2: CommonIdentity層の切り出し

APIファイルから処理を移動し、以下のような責務へ分けます。

- 入力正規化
- 共通ユーザー解決
- 身元情報照合
- 外部システムアカウント紐づけ
- レスポンス整形

この段階でも外部APIのURLとレスポンス互換は維持します。

### PR-B3: 代理店紐づけの分離

`common_users` 上の代理店フィールドを即削除せず、まずは互換表示用として残しながら、実際の代理店関係の主データを `agency_customer_relations` 側へ寄せます。

## まず実装するなら

最初に進めるべき作業は、PR-B1の契約テスト追加です。

理由:

- DB変更なしで安全に進められる
- 既存連携を壊していないか確認できる
- その後の大きな分離作業の失敗を見つけやすくなる
- 外部開発者向け仕様の根拠にも使える

## 2026-08-17 実施内容

PR-B1の入口として、DBに触らない契約テストを追加しました。

対象ファイル:

- `tests/Characterization/CommonIdentityFoundationTest.php`

追加した確認:

- `system_key` を正式キーとして優先すること
- `service_key` は既存互換として残ること
- 代理店項目がなくても一般ユーザー入力を正規化できること
- 既存の共通ID・紹介API入口が残っていること
- `api/common-users/index.php` がリクエスト時にDDLを実行しないこと

構文チェック:

- `tests/Characterization/CommonIdentityFoundationTest.php`: OK
- `api/common-users/index.php`: OK
- `src/CommonIdentity/CommonUserInputNormalizer.php`: OK
- `src/CommonIdentity/CommonUserInput.php`: OK

未実施:

- PHPUnit実行。対象フォルダに `vendor/bin/phpunit` が存在しないため未実施です。

## 今回はまだ実施しないこと

- 既存API URLの変更
- `common_users` から代理店カラムを削除
- 本番DBマイグレーション追加
- 外部連携仕様の破壊的変更
- SSO仕様の変更
- LPや紹介URLの仕様変更

## 実装可否

実装可能です。

ただし、次の順序で進めるのが安全です。

1. 契約テストを追加する
2. 共通ID処理をクラスへ切り出す
3. 代理店関係を別コンテキストへ分離する
4. 既存API互換を維持したまま内部構造を整理する
5. 最後に外部開発者向けドキュメントを更新する
