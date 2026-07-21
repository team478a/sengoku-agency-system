# AIアート教室 v3連携 既存データ移行計画

## 1. 目的

AIアート教室の既存データを保持したまま、5システム共通の `tenant_key`、`ai_art_member_id`、`common_user_id`、紹介関係、注文、受講権へ段階移行します。

この移行では、推測による共通ID統合、紹介者確定、販売担当確定、決済確定、受講権付与を行いません。

## 2. 移行原則

1. 既存レコードを削除しない
2. 移行前バックアップを取得する
3. 追加列と対応表を先に作り、既存列を直ちに置換しない
4. 自動一致は一意かつ検証可能な場合に限る
5. 不一致・複数候補は `review_required` とする
6. 各処理にバッチID、実行者、実行日時、変更前後を記録する
7. バッチ単位で再実行・ロールバック可能にする
8. テナントごとに件数・金額・残数を照合する

## 3. 追加する管理テーブル

### 3.1 `integration_user_mappings`

| 列 | 用途 |
|---|---|
| `id` | 主キー |
| `tenant_id` | AIアート教室テナントID |
| `tenant_key` | 外部連携用テナントキー |
| `ai_art_member_id` | AIアート教室会員ID |
| `common_user_id` | 共通ユーザーID |
| `mapping_status` | `pending` / `matched` / `created` / `review_required` / `unresolved` |
| `match_method` | `external_id` / `line` / `email` / `phone` / `manual` |
| `confidence` | 判定根拠の表示用。自動確定条件には単独使用しない |
| `resolved_at` | 照合完了日時 |
| `resolved_by` | API、バッチ、管理者ID |
| `migration_batch_id` | 移行バッチID |

一意制約:

- `(tenant_id, ai_art_member_id)`
- `(tenant_id, common_user_id)` は業務上1対1とする場合のみ付与する

### 3.2 `integration_referral_mappings`

| 列 | 用途 |
|---|---|
| `tenant_id` | テナント |
| `common_user_id` | 共通ユーザー |
| `registration_referrer_agent_code` | 登録時紹介者 |
| `sales_agent_code` | 販売担当 |
| `assigned_agent_code` | 現在担当 |
| `source_system` | 確定情報の取得元 |
| `status` | `pending` / `confirmed` / `review_required` |
| `confirmed_at` | 確定日時 |

既存の単一 `agent_code` がどの意味か判断できない場合は、3項目へ複製しません。`review_required` として元値を監査ログへ残します。

### 3.3 `integration_entitlement_mappings`

| 列 | 用途 |
|---|---|
| `tenant_id` | テナント |
| `common_user_id` | 共通ユーザー |
| `source_order_id` | ショッピング注文ID |
| `entitlement_id` | 受講権ID |
| `local_source_type` | 既存サブスク、回数券、一回払い、初回無料等 |
| `local_source_id` | 既存レコードID |
| `quantity_granted` | 付与数 |
| `quantity_remaining` | 残数 |
| `valid_from` | 開始日時 |
| `valid_until` | 有効期限 |
| `status` | `pending` / `active` / `revoked` / `review_required` |
| `migration_batch_id` | 移行バッチID |

### 3.4 `integration_migration_batches`

| 列 | 用途 |
|---|---|
| `batch_id` | UUID |
| `tenant_id` | 対象テナント |
| `migration_type` | `tenant` / `identity` / `referral` / `order` / `entitlement` |
| `status` | `planned` / `running` / `completed` / `failed` / `rolled_back` |
| `dry_run` | ドライランか |
| `started_at` / `finished_at` | 実行時刻 |
| `summary_json` | 件数・警告・エラー集計 |
| `created_by` | 実行者 |

### 3.5 `integration_migration_items`

レコード単位の変更前後、結果、エラーコード、再実行回数を保存します。個人情報の平文コピーは避け、対象テーブル・対象ID・ハッシュ・変更項目を記録します。

## 4. 移行前監査

テナントごとに次を集計します。

- ユーザー総数
- `tenant_id` 未割当数
- LINE User IDの重複数
- メール・電話の重複数
- 予約、出席、生成依頼、画像の件数
- 支払済み、返金済み、キャンセルの件数と金額
- サブスク有効数
- 回数券の付与数、消費数、残数
- 初回無料の利用済み数
- 紹介コードの設定数と重複・不明数
- 画像・アップロードファイルの欠損数

監査結果はCSVと管理画面サマリーの両方で保存します。

## 5. フェーズA: テナント確定

### 自動確定条件

- 現行テナント列が設定済みで、有効なテナントと一致する
- 単一ドメイン・単一LINEチャネルなど、既存構成から一意に判定できる

### 要確認条件

- テナント未割当で複数候補がある
- LINE User IDまたは設定キーが複数テナントで共有されている
- 予約・決済・生成物の所属テナントがユーザー所属と矛盾する

テナント未確定レコードは後続の共通ID移行へ進めません。

## 6. フェーズB: AIアート会員ID確定

- 既存ユーザー主キーを変更しない
- 外部公開用に `ai_art_member_id` を発行する
- 推奨形式: 推測困難なUUIDまたはULID
- テナント内で一意制約を付与する
- URLやWebhookで内部連番を公開しない

## 7. フェーズC: 共通ID照合

### 7.1 照合優先順位

1. 既存の確定済み外部IDマッピング
2. テナントとLINEチャネルを含むLINE User IDの一意一致
3. 正規化済みメールの一意一致
4. 正規化済み電話番号の一意一致
5. 管理者による本人確認

氏名だけでは自動一致させません。

### 7.2 判定

| 条件 | 結果 |
|---|---|
| 一意一致し、矛盾なし | `matched` |
| 共通基盤に該当なし、新規作成許可あり | `created` |
| 複数候補または項目矛盾 | `review_required` |
| 照合情報不足 | `unresolved` |

### 7.3 確定後

- `integration_user_mappings` を更新する
- `/api/v2/user-mappings` へ確定対応を登録する
- 共通ID基盤の応答を監査ログへ記録する
- 既存ユーザーテーブルには参照用 `common_user_id` を反映する

## 8. フェーズD: 紹介・担当移行

1. 共通基盤に確定済み関係がある場合はそれを採用する
2. 既存AIアート教室の値は候補として比較する
3. 一致しない場合は共通基盤を上書きせず `review_required` とする
4. 登録紹介者、販売担当、現在担当を別々に保存する
5. 生の紹介コード、URLパラメータ、未検証トークンだけでは確定しない

## 9. フェーズE: 注文・決済照合

- Stripe Payment Intent、Checkout Session、Invoice、Customer等の既存IDを候補に使用する
- ショッピングシステムの注文と一意に一致した場合のみ `source_order_id` を設定する
- 金額、通貨、支払日時、返金額、共通ユーザーを照合する
- ローカルで支払済みでもショッピング側に存在しない場合は移行保留とする
- 現金・管理者手動付与は専用の移行区分で記録し、Stripe決済として扱わない

## 10. フェーズF: 受講権移行

### 10.1 変換対象

- 月額・年額サブスク
- 回数券
- 一回払い参加権
- 初回無料
- 管理者手動付与
- ガチャ等で付与された権利

### 10.2 照合式

```text
移行前残数 = 総付与数 - 正常消費数 + 正常取消戻し数
移行後残数 = activeなentitlementのquantity_remaining合計
```

移行前後が一致しないユーザーは有効化せず、要確認一覧へ送ります。

## 11. ドライラン

本実行前に必ずドライランを行います。

ドライランではDB更新や外部API確定登録をせず、次を出力します。

- 自動確定予定件数
- 新規共通ID作成予定件数
- 要確認件数
- 未解決件数
- 紹介関係差異
- 注文金額差異
- 受講権残数差異
- テナント不整合
- API呼出予定件数

## 12. 本実行

1. メンテナンスまたは対象テナントの更新停止
2. DB・設定・アップロードのバックアップ
3. バッチ作成
4. テナント移行
5. 会員ID発行
6. 共通ID照合
7. 紹介・担当移行
8. 注文・決済照合
9. 受講権移行
10. 件数・金額・残数照合
11. スモークテスト
12. 対象テナントを再開

## 13. ロールバック

- 追加した対応表をバッチID単位で無効化する
- 既存列へ反映した値は `integration_migration_items` の変更前値へ戻す
- 外部基盤へ登録済みの対応は削除せず、取消・無効化APIを使用する
- 既存予約、決済、生成物そのものはロールバックで削除しない
- ロールバック理由と実行者を監査ログへ残す

## 14. 完了条件

- テナント未割当が0件、または承認済み例外のみ
- 自動確定した共通IDに矛盾がない
- 要確認・未解決件数が一覧化されている
- 紹介者・販売担当・現在担当が分離されている
- 注文件数と金額が移行前後で一致する
- 受講権の付与・消費・残数が一致する
- 同一バッチを再実行しても二重登録されない
- 主要画面、LINE、予約、出席、画像生成、購入のスモークテストが成功する
- テナント間のデータ漏えいテストが成功する

## 15. 実装順序

1. 管理テーブルと監査レポート
2. テナント監査・補正
3. 共通IDドライラン
4. 要確認管理画面
5. 共通ID本移行
6. 紹介・担当移行
7. 注文・決済照合
8. 受講権移行
9. 結合試験
10. テナント単位の段階リリース
