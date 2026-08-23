# 報酬CSV取込 運用手順書

Version: 3.6.166

## 目的

この機能は、外部で計算した報酬CSVを代理店システムへ安全に取り込むためのものです。

支払い申請、承認、支払済み登録などの支払い機能は含めません。報酬額が確定したCSVを、代理店ごとの表示・集計に反映するところまでが対象です。

## できること

- CSVの事前チェック
- 取込前プレビュー
- エラー行の表示
- 同じCSVファイルの二重取込防止
- 同じ報酬キーの二重取込防止
- 取込履歴の確認
- 代理店別の報酬元帳への反映

## 管理画面

管理画面のサイドメニューから以下を開きます。

- 成果・報酬
- 報酬CSV取込

URL:

```text
/admin/rewards.php
```

## 利用前チェック

取込前に次を確認してください。

1. 管理画面のアップデートで、未適用Migrationが0になっている
2. 報酬CSV画面が表示できる
3. 代理店コードがメンバー管理に存在している
4. 同じCSVをすでに取り込んでいない
5. 支払確定前の確認用CSVではなく、取込対象の確定CSVである

未適用Migrationに `3.6.165` が残っている場合は、先にDBマイグレーションを適用してください。

## 必須列

CSVには最低限、次の列が必要です。

| 列名 | 内容 |
| --- | --- |
| external_reward_key | 報酬行を一意に識別する外部キー |
| agent_code | 報酬を紐づける代理店コード |
| amount または amount_minor | 報酬金額 |

`external_reward_key` は、二重取込を防ぐために必須です。同じキーは再取込できません。

## 任意列

| 列名 | 内容 |
| --- | --- |
| currency | 通貨。未指定時は JPY |
| occurred_at | 発生日 |
| source_system_key | 送信元システム |
| project_key | プロジェクト識別子 |
| product_code | 商品コード |
| order_id | 注文ID |
| order_item_id | 注文明細ID |
| common_user_id | 共通顧客ID |
| status | confirmed / cancelled / adjustment |
| description | 内容・メモ |

## 日本語ヘッダー

一部の日本語ヘッダーにも対応しています。

| 日本語ヘッダー例 | 対応する列 |
| --- | --- |
| 外部キー / 報酬キー / 外部一意キー | external_reward_key |
| 代理店コード | agent_code |
| 金額 | amount |
| 金額（最小単位） | amount_minor |
| 通貨 | currency |
| 発生日 | occurred_at |
| 送信元 / 連携元 | source_system_key |
| プロジェクト | project_key |
| 商品コード | product_code |
| 注文ID | order_id |
| 注文明細ID | order_item_id |
| 共通顧客ID | common_user_id |
| 状態 | status |
| メモ / 説明 | description |

## 取込手順

1. 報酬計算済みCSVを用意します。
2. 管理画面の「成果・報酬」から「報酬CSV取込」を開きます。
3. 必要に応じてサンプルCSVをダウンロードし、列名を合わせます。
4. CSVファイルを選択して事前チェックを実行します。
5. プレビューで、件数・代理店コード・金額・状態を確認します。
6. エラーがある場合はCSVを修正して、再度アップロードします。
7. エラーが0件になったら取り込みを実行します。
8. 取込履歴と代理店別の反映結果を確認します。

## 取込ルール

- エラー行が1件でもある場合、取込はできません。
- CSVを修正して、再度アップロードしてください。
- 同じ external_reward_key は再取込できません。
- 同じCSVファイルは再取込できません。
- agent_code が存在しない場合はエラーになります。
- 金額が数値として読めない場合はエラーになります。
- 報酬元帳は上書きしません。訂正は取消・調整の行を追加して処理します。
- メールアドレス、電話番号、LINE IDなどの個人情報はCSVへ入れないでください。

## 金額の扱い

`amount` と `amount_minor` のどちらかを指定できます。

| 列 | 用途 |
| --- | --- |
| amount | 円単位で入力する場合に使います。例: `1200` |
| amount_minor | 最小通貨単位で入力する場合に使います。JPYなら `120000` は1,200円相当です。 |

両方が指定された場合は、CSV作成元の仕様を統一するため、どちらか一方だけにしてください。

## 状態の扱い

| status | 意味 |
| --- | --- |
| confirmed | 確定報酬 |
| cancelled | 取消 |
| adjustment | 調整 |

日本語の「確定」「取消」「調整」にも対応します。

## よくあるエラー

| 表示 | 原因 | 対応 |
| --- | --- | --- |
| 必須列がありません | CSVヘッダーが不足しています | サンプルCSVに合わせて列を追加します |
| 代理店コードが存在しません | agent_codeがメンバー管理にありません | メンバー管理で正しいコードを確認します |
| 外部一意キーが重複しています | すでに取り込んだ報酬行です | 新しい報酬行には別のキーを付けます |
| 同じCSVファイルです | 同一ファイルを再取込しようとしています | 取込履歴を確認します |
| 金額が不正です | 金額欄が空、文字列、または形式不正です | 数値で入力します |

## サンプルCSV

管理画面からサンプルCSVをダウンロードできます。

```text
/admin/rewards.php?sample=csv
```

例:

```csv
external_reward_key,agent_code,amount,currency,occurred_at,source_system_key,project_key,product_code,order_id,status,description
passport-202608-0001,agent_7_8573,1200,JPY,2026-08-23,SENGOKU_PASSPORT,sengoku-influencer,PASS-001,ORD-1001,confirmed,8月分確定報酬
```

## DB変更

追加テーブル:

- reward_import_batches
- agent_reward_ledger

既存テーブル補完:

- external_product_rules.target_service
- external_product_rules.description

## 注意

この機能は「報酬結果の取込」までです。

支払い申請、支払承認、支払済み登録は今回の対象外です。

実際の振込処理は、別途ダウンロードした銀行情報や会計側の管理に従って実施してください。
