# 報酬CSV取込 運用メモ

Version: 3.6.165

## 目的

この機能は、外部で計算した報酬CSVを代理店システムへ安全に取り込むためのものです。

支払い申請、承認、支払済み登録などの支払い機能は含めません。

## できること

- CSVの事前チェック
- 取込前プレビュー
- エラー行の表示
- 同じCSVファイルの二重取込防止
- 同じ報酬キーの二重取込防止
- 取込履歴の確認

## 管理画面

管理画面のサイドメニューから以下を開きます。

- 成果・問い合わせ
- 報酬CSV

URL:

```text
/admin/rewards.php
```

## 必須列

CSVには最低限、次の列が必要です。

| 列名 | 内容 |
| --- | --- |
| external_reward_key | 報酬行を一意に識別する外部キー |
| agent_code | 報酬を紐づける代理店コード |
| amount または amount_minor | 報酬金額 |

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

例:

- 外部キー
- 報酬キー
- 代理店コード
- 金額
- 通貨
- 発生日
- 送信元
- プロジェクト
- 商品コード
- 注文ID
- 共通顧客ID
- 状態
- メモ

## 取込ルール

- エラー行が1件でもある場合、取込はできません。
- CSVを修正して、再度アップロードしてください。
- 同じ external_reward_key は再取込できません。
- 同じCSVファイルは再取込できません。
- agent_code が存在しない場合はエラーになります。
- 金額が数値として読めない場合はエラーになります。

## サンプルCSV

管理画面からサンプルCSVをダウンロードできます。

```text
/admin/rewards.php?sample=csv
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
