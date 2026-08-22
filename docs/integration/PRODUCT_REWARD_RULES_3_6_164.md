# 商品・報酬対象ルール 3.6.164

## 目的

外部サービスから届く販売・申込イベントを、代理店システム側の商品ルールで判定できるようにする。

外部サービスは販売事実だけを送信し、報酬対象かどうかは代理店システムで管理する。

## 管理画面

管理者画面の `LP・プロジェクト > 商品・報酬ルール` から設定する。

URL:

```text
/admin/product_rules.php
```

## 一意条件

商品ルールは次の組み合わせで一意に管理する。

```text
source_system_key + product_code
```

例:

```text
AI_ART_SCHOOL + ai-art-course
SENGOKU_SHOPPING + product-basic
SENGOKU_PASSPORT + passport-yearly
```

## 報酬対象区分

| 値 | 意味 |
| --- | --- |
| `ELIGIBLE` | 報酬対象 |
| `NOT_ELIGIBLE` | 報酬対象外 |
| `UNKNOWN` | 未判定。自動で報酬対象にしない |

未登録商品の販売事実は破棄せず、`integration_inbox_events.processing_status = blocked_unknown_product` として保存する。

## AIアート教室

AIアート教室の受講料金は、代理店紐づけ対象だが報酬対象外とする。

初期ルール:

```text
source_system_key: AI_ART_SCHOOL
product_code: ai-art-course
reward_eligibility: NOT_ELIGIBLE
refund_policy: revoke_entitlement
```

## 追加DB

### external_product_rules

外部サービスの商品コードごとの判定ルールを管理する。

主な項目:

| 項目 | 内容 |
| --- | --- |
| `source_system_key` | 外部サービスキー |
| `product_code` | 外部サービス側の商品コード |
| `display_name` | 管理画面表示名 |
| `project_id` / `project_key` | 紐づくプロジェクト |
| `validity_days` | 利用権の有効期間 |
| `reward_eligibility` | 報酬対象区分 |
| `entitlement_type` | 利用権種別 |
| `target_service` | 対象サービス |
| `refund_policy` | 返金時の扱い |

### integration_inbox_events 追加列

| 項目 | 内容 |
| --- | --- |
| `product_rule_id` | 判定に使った商品ルール |
| `eligibility_reason` | 判定理由 |

## 更新ファイル

```text
VERSION
admin/header.php
admin/product_rules.php
api/integrations/events/index.php
config/migrations/3.6.164.sql
config/setup.sql
includes/functions.php
docs/integration/PRODUCT_REWARD_RULES_3_6_164.md
```
