# ORLYポイント紹介付与機能 事前確認レポート

作成日: 2026-08-29  
対象リポジトリ: `team478a/sengoku-agency-system`  
確認対象資料: `SENGOKU_ORLY_POINT_REFERRAL_IMPLEMENTATION_INSTRUCTIONS_20260829.md`

## 0. 作業範囲

本レポートは、ORLYポイント紹介付与機能を実装する前の確認結果である。

今回実施したのはローカルリポジトリの確認とレポート作成のみ。コード変更、DBマイグレーション作成・適用、本番・ステージングデータ変更、機能フラグ変更、外部送信開始、デプロイは行っていない。

## 1. 現在のリポジトリ状態

| 項目 | 確認結果 |
|---|---|
| ローカルリポジトリ | `C:\Users\COOLWORKS\OneDrive\ドキュメント\GitHub\sengoku-agency-system` |
| GitHub | `https://github.com/team478a/sengoku-agency-system.git` |
| ブランチ | `main` |
| 最新コミット | `775f4a6 Polish reward CSV docs and admin menu labels` |
| ローカルバージョン | `3.6.168` |
| 未コミット変更 | あり |

確認時点で、以下の未コミット変更が存在する。

- `CHANGELOG.md`
- `VERSION`
- `admin/templates.php`
- `config/setup.sql`
- `config/migrations/3.6.167.sql`
- `config/migrations/3.6.168.sql`
- `templates/sen-no-kuni-influencer-activity/`
- `templates/sen-no-kuni-influencer-men/`
- `templates/sen-no-kuni-influencer-women-30-40/`
- `templates/sen-no-kuni-influencer-women/`

ORLYポイント機能の実装を始める前に、この差分を先に整理・コミット・プッシュするか、別ブランチへ分離することを推奨する。

## 2. 既存実装で利用できる基盤

### 2.1 紹介確定API

実装場所:

- `api/referrals/index.php`

既存の `confirm` 処理で、紹介セッション、共通ユーザー、代理店紐づけ、利用権付与、取引保存まで行っている。ORLYポイント付与の起点として利用できる。

主な既存処理:

- `referrals/capture`
- `referrals/confirm`
- `referral_sessions`
- `agency_customer_relations`
- `common_users.registration_referrer_agent_id`
- `agent_touchpoints`
- `customer_transactions`
- `customer_entitlements`

### 2.2 共通ID基盤

実装場所:

- `api/common-users/index.php`
- `src/CommonIdentity/CommonUserResolveService.php`
- `includes/functions.php`

`common_user_id` を中心に、外部サービスのユーザーIDと代理店システム側のユーザーを紐づける基盤が存在する。

### 2.3 代理店階層

既存データ構造として、以下を利用できる。

- `agents.level`
- `agents.parent_id`
- `agents.position_type`

現在の役職には、エージェント、エージェント候補、ディレクター、アドバイザー、スーパーアドバイザー、インフルエンサーが含まれる。ユーザー方針により、エージェント候補はディレクターを下につけられるため、階層上はエージェント相当として扱う必要がある。

### 2.4 外部連携・Outbox

実装場所:

- `includes/functions.php`
- `src/Integration/Outbox/OutboxRepository.php`
- `src/Integration/Outbox/OutboxClaimService.php`
- `src/Integration/Outbox/DeadLetterService.php`
- `cron/external_integration_retry.php`
- `admin/external_partners.php`
- `admin/external_integration_logs.php`

連携先別APIキー、Bearer、HMAC、IP制限、Outbox、再送、DLQの基盤が存在する。Walletへの送信も、この基盤を利用できる可能性が高い。

ただし、資料では「ウォレット連携アダプタを先に作る」「URLや認証を直書きしない」とあるため、ORLY専用の送信アダプタを薄く追加し、既存Outboxへ流す構成が安全。

### 2.5 現金報酬CSV基盤

実装場所:

- `admin/reward_imports.php`
- `admin/reward_ledger.php`
- `config/migrations/3.6.165.sql`

`agent_reward_ledger` は現金報酬CSV用の元帳。ORLYポイントはウォレット側の公式残高が正となるため、このテーブルを流用しない方針でよい。

## 3. 指示書との差分

| 項目 | 現状 | 必要対応 |
|---|---|---|
| ORLY通貨マスター | 未実装 | `point_currencies` 追加 |
| ポイント施策管理 | 未実装 | 管理画面 `ポイント施策管理` 追加 |
| 施策バージョン | 未実装 | `point_campaigns` / `point_campaign_versions` 追加 |
| ポイント付与イベント | 未実装 | `point_award_events` 追加 |
| 手動調整 | 未実装 | `point_adjustments` 追加 |
| 施策監査ログ | 未実装 | `point_setting_audit_logs` 追加 |
| ORLY feature flag | 未実装 | 3つのフラグを既定OFFで追加 |
| 直接紹介者判定 | 紹介元保存はあり | 判定サービスとして切り出し |
| 上位ディレクター判定 | 親子階層はあり | `level=1` の親 `level=2` を判定 |
| Wallet送信 | 汎用外部連携はあり | Walletアダプタ + Outboxイベント追加 |
| 二重付与防止 | Idempotency基盤はあり | ORLY専用一意キーを追加 |
| 管理画面 | 未実装 | 施策・付与履歴・未送信・DLQを表示 |

## 4. 想定される書込み経路

推奨する流れは以下。

1. 外部サービスまたはLP経由で `referrals/confirm` が呼ばれる
2. `common_user_id` が解決される
3. `registration_referrer_agent_id` と紹介関係が保存される
4. ORLY施策が有効な場合のみ、ORLY付与判定サービスを呼ぶ
5. 新規登録者、直接紹介者、上位ディレクターの付与候補を作る
6. `point_award_events` に `PENDING` として保存する
7. Wallet送信フラグがONの場合のみ、Outboxへ送信イベントを登録する
8. Wallet側の応答結果を `point_award_events` と連携ログへ反映する

## 5. 重要な確認事項

実装前に確認したい点は以下。

1. ORLYポイント付与の起点は、`referrals/confirm` 成功時でよいか。
2. 「新規登録者」とは、新しい `common_user_id` が作られた人か、サービス登録が初回の人か。
3. ORLYポイント施策は全プロジェクト共通か、プロジェクトごとに有効・無効を分けるか。
4. エージェント候補が直接紹介者の場合、エージェントと同じ扱いで 3,000 OP のみ付与し、上位ディレクター 1,000 OP は付けない理解でよいか。
5. Walletへ送る正式エンドポイント、認証方式、必須payloadは別途確定が必要。
6. ポイント付与後に紹介関係が変更された場合、過去付与は販売時点・登録時点のスナップショットを正として変更しない方針でよいか。
7. 付与上限、保留期間、取消条件が必要か。

## 6. 推奨PR分割

### PR-O0: 事前確認

本レポートのみ。実装なし。

テスト方法:

- レポート内容のレビュー
- 既存未コミット差分の確認

ロールバック:

- 本レポートを削除

### PR-O1: DBとFeature Flag

追加内容:

- ORLYポイント用テーブル
- `orly_point_campaign_enabled`
- `orly_point_award_enabled`
- `orly_wallet_delivery_enabled`
- `config/setup.sql` 反映

影響範囲:

- DB定義のみ
- 既定OFFのため既存業務への影響なし

テスト方法:

- PHP lint
- 空DB適用
- 既存DBアップデート
- マイグレーション再実行

ロールバック:

- フラグOFF維持
- 未使用テーブルのため業務影響なし
- 必要に応じて追加テーブル削除SQLを別途実施

### PR-O2: 付与判定サービス

追加内容:

- 付与対象判定
- 直接紹介者判定
- 上位ディレクター判定
- エージェント候補の扱い
- Idempotency key生成

影響範囲:

- 新規サービス中心
- 既存APIにはまだ接続しない

テスト方法:

- 新規登録者のみ
- 紹介者あり
- 紹介者なし
- 紹介者がアドバイザー
- 紹介者がディレクター
- 紹介者がエージェント
- 紹介者がエージェント候補
- 退会・停止中代理店
- 二重実行

ロールバック:

- 呼び出し前のため新規コード削除のみ

### PR-O3: referrals/confirm への接続

追加内容:

- `referrals/confirm` 成功後にORLY付与イベントを作成
- フラグOFF時は完全に無動作
- エラー時に紹介確定処理を壊さない設計

影響範囲:

- 紹介確定API

テスト方法:

- 既存confirm互換性
- ORLYフラグOFF
- 施策OFF
- 付与イベント作成
- 重複confirm
- DB障害時の安全停止

ロールバック:

- ORLYフラグOFF
- 追加呼び出しを戻す

### PR-O4: 管理画面

追加内容:

- ポイント施策管理
- 施策バージョン作成
- 有効化・停止
- 付与履歴
- 手動調整
- 監査ログ

影響範囲:

- 管理画面のみ

テスト方法:

- 施策作成
- バージョン追加
- 有効化
- 停止
- 手動調整
- 二重確認
- 監査ログ

ロールバック:

- メニュー非表示
- フラグOFF

### PR-O5: Wallet送信

追加内容:

- Walletアダプタ
- Outboxイベント
- 接続テスト
- 再送・DLQ連携

影響範囲:

- 外部送信

テスト方法:

- mock Wallet
- 接続成功
- 接続失敗
- タイムアウト
- 401/403/409/429/503
- 再送
- DLQ

ロールバック:

- `orly_wallet_delivery_enabled` をOFF
- Outbox配送停止

## 7. 直近の推奨手順

1. 既存未コミット変更を先に整理する。
2. ORLYポイント機能用の作業ブランチを切る。
3. PR-O1から開始し、DBとFeature Flagだけを追加する。
4. Wallet連携仕様が未確定でも、PR-O2まではmock前提で進められる。
5. Wallet送信をONにするのは、Wallet側の受信仕様と接続テスト完了後にする。

## 8. 結論

ORLYポイント紹介付与機能は、既存の共通ID、紹介確定、代理店階層、Outbox基盤を利用して実装可能。

ただし、公式ポイント残高はWallet側が正であり、代理店システムでは「付与対象の判定」「付与イベントの記録」「Walletへの配送管理」までに限定するのが安全である。

現時点ではORLY専用のDB、Feature Flag、管理画面、Wallet送信アダプタが不足している。既存の現金報酬CSV機能とは分離して追加する必要がある。

## 9. 確認コマンド結果

ローカル環境では `composer` コマンドが利用できなかったため、PHP構文チェック用スクリプトを直接実行した。

結果:

- `php scripts/lint-php.php`: 成功

補足:

- `composer run lint` は `composer` 未認識のため未実行。
- PHPUnit、PHPStan、DBマイグレーション適用テストは未実行。

## 10. PR-O1相当の実装メモ

ユーザー承認後、PR-O1相当としてORLYポイント紹介付与のDB土台とFeature Flagを追加した。

追加・変更ファイル:

- `config/migrations/3.6.169.sql`
- `config/setup.sql`
- `includes/functions.php`
- `CHANGELOG.md`
- `VERSION`

追加したDB定義:

- `point_currencies`
- `point_campaigns`
- `point_campaign_versions`
- `point_award_events`
- `point_adjustments`
- `point_setting_audit_logs`

追加したFeature Flag:

- `orly_point_campaign_enabled`
- `orly_point_award_enabled`
- `orly_wallet_delivery_enabled`

安全設計:

- すべて既定値はOFF。
- 初期キャンペーン `orly_referral_signup` は `draft` 状態。
- Wallet送信はまだ行わない。
- 紹介確定APIへの接続、付与判定、管理画面、Wallet連携は次PR以降で実装する。

確認結果:

- `php -l includes/functions.php`: 成功
- `php scripts/lint-php.php`: 成功

未実施:

- 本番DBへのマイグレーション適用
- 空DB適用テスト
- 既存DBアップグレードテスト
- PHPUnit、PHPStan

## 11. PR-O2相当の実装メモ

ユーザー承認後、PR-O2相当としてORLY紹介登録時の付与候補判定ロジックを追加した。

追加ファイル:

- `src/Point/OrlyReferralPointAwardPlanner.php`
- `tests/Characterization/OrlyReferralPointAwardPlannerTest.php`

実装内容:

- 新規登録者、直接紹介者、上位ディレクターの付与候補を作成する。
- `agent_candidate` は直接紹介者としてエージェント相当で扱う。
- 上位ディレクター付与は、祖先にディレクターがいる場合のみ候補にする。
- `award_event_key` はイベントID、キャンペーン、受取種別などから生成し、個人情報を含めない。

安全設計:

- DB書き込み、紹介確定API、Wallet送信にはまだ接続していない。
- Feature FlagをONにしない。
- 既存の紹介登録、報酬CSV、外部連携Outboxには影響しない。

確認結果:

- `php -l src/Point/OrlyReferralPointAwardPlanner.php`: 成功
- `php -l tests/Characterization/OrlyReferralPointAwardPlannerTest.php`: 成功
- `php scripts/lint-php.php`: 成功

未実施:

- PHPUnitはローカルに未導入のため未実行。
- 紹介確定APIとの接続テストは未実施。
- Wallet送信テストは未実施。

## 12. PR-O3相当の実装メモ

ユーザー承認後、PR-O3相当としてORLYポイント付与候補をDBへ冪等に保存する部品を追加した。

追加ファイル:

- `src/Point/PointAwardEventRepository.php`

実装内容:

- `point_award_events.award_event_key` を使って同一付与イベントの二重作成を防止する。
- 同じ `award_event_key` かつ同じ内容の場合は冪等成功として扱う。
- 同じ `award_event_key` で内容が異なる場合は `conflict` として返し、自動上書きしない。
- 保存時に `payload_hash` を記録し、後続の再実行・再送時に内容差分を検知できるようにした。

安全設計:

- Wallet送信、Outbox作成、既存紹介確定APIへの自動接続はまだ行わない。
- Feature FlagをONにしない。
- メール、電話、LINE IDなどの個人情報をキー生成やpayload hashの対象にしない。

確認結果:

- `php -l src/Point/PointAwardEventRepository.php`: 成功
- `php -l src/Point/OrlyReferralPointAwardPlanner.php`: 成功
- `php scripts/lint-php.php`: 成功

未実施:

- 本番DBへのマイグレーション適用
- DB統合テスト
- 紹介確定APIへの接続
- Wallet送信

## 13. PR-O4相当の実装メモ

ユーザー承認後、PR-O4相当として紹介確定APIからORLYポイント付与候補を保存する接続を追加した。

追加ファイル:

- `src/Point/OrlyReferralPointCampaignRepository.php`
- `src/Point/OrlyReferralPointAwardService.php`

変更ファイル:

- `includes/shared_bootstrap.php`
- `api/referrals/index.php`
- `src/Point/PointAwardEventRepository.php`

実装内容:

- `referrals/confirm` の紹介確定処理内で、ORLYポイント付与候補を `point_award_events` に保存できるようにした。
- 保存は `orly_point_campaign_enabled` と `orly_point_award_enabled` が両方ONの場合だけ動作する。
- キャンペーンとバージョンが `active` の場合だけ付与候補を作る。
- DBテーブルが未作成、またはキャンペーンが未公開の場合は、既存の紹介確定処理を止めずにスキップする。
- `agent_candidate` は直接紹介者としてエージェント相当で扱う。
- 上位ディレクター付与は祖先にディレクターがいる場合のみ作成する。

安全設計:

- Wallet送信はまだ接続していない。
- Outbox配送はまだ行わない。
- Feature Flagの既定値はOFFのまま。
- キャンペーン初期状態は `draft` のまま。
- 既存APIレスポンスは維持し、ORLYポイント処理が動作した場合のみ `orly_point_awards` を追加する。

確認結果:

- `php -l src/Point/OrlyReferralPointCampaignRepository.php`: 成功
- `php -l src/Point/OrlyReferralPointAwardService.php`: 成功
- `php -l src/Point/PointAwardEventRepository.php`: 成功
- `php -l includes/shared_bootstrap.php`: 成功
- `php -l api/referrals/index.php`: 成功
- `php scripts/lint-php.php`: 成功

未実施:

- 本番DBへのマイグレーション適用
- DB統合テスト
- Wallet送信
- Outbox配送
- PHPUnitはローカルに未導入のため未実行
