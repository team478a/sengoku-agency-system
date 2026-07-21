# AIアート教室 5システム連携ガイド

## 1. 文書の位置づけ

- 文書版: v3.0
- 基準文書: `千ノ国 5システム共通認識・連携方針書 完全版 v3`
- 対象: 代理店システム、千ノ国パスポート、ショッピングシステム、千ノ国ウォレット、AIアート教室
- 状態: 実装契約案。各システムの既存APIを一度に変更せず、本文の移行順序に従って段階導入する。

本書は、AIアート教室を5システム連携へ参加させるための境界、識別子、API、Webhook、移行方法を定義します。

共通ID、紹介関係、販売担当、決済、受講権、ポイントの正本は、AIアート教室側の独自判断で変更しません。現行仕様と目標仕様が異なる箇所は、「現行」と「目標契約」を明記します。

## 2. 変更してはいけない正本

| 情報 | 正本システム | AIアート教室の役割 |
|---|---|---|
| 共通ユーザーID | 共通ID基盤 | 照合結果を保存して利用する |
| 確定した紹介関係 | 共通ID基盤・代理店システム | 紹介トークンを提示し、確定結果を受け取る |
| 代理店階層・現在の担当 | 代理店システム | 参照する。直接更新しない |
| 注文・決済・返金 | ショッピングシステム | 決済結果を受け取る。ローカルStripe結果を全体の正本にしない |
| 受講権・回数券等の付与 | ショッピングシステムまたは共通受講権基盤 | 付与・取消イベントを受け取り、利用時に消費要求を送る |
| ポイント・クーポン・ガチャ残高 | 千ノ国ウォレット | 残高を直接更新しない |
| 教室予約・出席・画像生成 | AIアート教室 | ローカル業務の正本として管理する |

## 3. AIアート教室が保持する識別子

### 3.1 必須識別子

| 項目 | 用途 | 必須時期 |
|---|---|---|
| `tenant_key` | AIアート教室内のクライアント識別 | 全リクエスト・全データ |
| `project_key` | 5システム共通の案件識別 | 共通連携時 |
| `ai_art_member_id` | AIアート教室内の会員ID | 会員作成時 |
| `common_user_id` | 5システム共通ユーザーID | 共通ID照合完了後 |
| `line_user_id` | テナントのLINE公式アカウント上のユーザーID | LINE経由時 |

`common_user_id` は未照合中のみNULLを許可します。照合完了後の主要な連携処理では必須です。

### 3.2 紹介・担当識別子

次の3項目を混同しません。

| 項目 | 意味 |
|---|---|
| `registration_referrer_agent_code` | 本登録時に確定した紹介者 |
| `sales_agent_code` | 対象注文・契約の販売担当 |
| `assigned_agent_code` | 現在の運用担当者 |

AIアート教室はこれらを独自確定しません。共通ID基盤、代理店システム、ショッピングシステムから受け取った結果を保存します。

### 3.3 決済・受講権識別子

| 項目 | 意味 |
|---|---|
| `source_order_id` | ショッピングシステムの注文ID |
| `payment_id` | 決済識別子 |
| `entitlement_id` | 受講権・利用権の一意ID |
| `entitlement_type` | `subscription`、`ticket`、`single`、`trial`等 |

## 4. 現行APIと目標契約

### 4.1 APIの役割

既存の2系統は競合させず、用途を分けます。

| API | 正式な役割 |
|---|---|
| `/api/common-users/resolve` | 外部識別子から共通ユーザーを照合し、必要な場合は作成候補を返す |
| `/api/v2/user-mappings` | 共通ユーザーと外部システムIDの明示的な紐付け登録・照会 |

`resolve` は本人照合、`user-mappings` は確定済み対応表の管理に使用します。

### 4.2 現行API

現在、代理店システムには少なくとも次のエンドポイントがあります。

- `/api/common-users/resolve`
- `/api/v2/user-mappings`
- `/api/referrals/capture`
- `/api/referrals/confirm`
- `/api/sso/jwks.php`

現行のリクエスト形式は既存利用元を壊さないため当面維持します。新規連携は次節の目標契約へ寄せます。

## 5. 共通ID解決

### 5.1 目標リクエスト

```http
POST /api/common-users/resolve
Content-Type: application/json
X-Sengoku-Key-Id: ai-art/{tenant_key}
X-Sengoku-Timestamp: 2026-07-20T10:00:00+09:00
X-Sengoku-Signature: v1={hmac_sha256}
Idempotency-Key: {uuid}
```

```json
{
  "tenant_key": "kotoa",
  "project_key": "sengoku",
  "source_system": "ai_art_school",
  "external_user_id": "12345",
  "line_user_id": "Uxxxxxxxx",
  "email": "user@example.com",
  "phone": "09000000000",
  "referral_token": "signed-expiring-token"
}
```

生の `agent_code` は照合要求だけで紹介関係を確定する根拠にしません。紹介候補は署名付き・期限付きの `referral_token` で渡します。

### 5.2 目標レスポンス

```json
{
  "ok": true,
  "common_user_id": "CU-000001",
  "match_status": "matched",
  "mapping_status": "linked",
  "referral_status": "confirmed",
  "registration_referrer_agent_code": "AG-001",
  "assigned_agent_code": "AG-010"
}
```

`match_status` は以下を使用します。

- `matched`: 一意に照合済み
- `created`: 新規共通IDを作成済み
- `review_required`: 複数候補または不整合のため人手確認が必要
- `unresolved`: 照合不能

`review_required` と `unresolved` の場合、AIアート教室は紹介・決済・受講権を確定扱いにしません。

## 6. 紹介関係

### 6.1 優先順位

紹介関係は次の順で決定します。

1. すでに共通基盤で確定済みの紹介関係
2. 署名検証と有効期限確認に成功した `referral_token`
3. 紹介者なし

URLやフォームから受け取った生の代理店コードのみで確定しません。

### 6.2 紹介候補の捕捉

```http
POST /api/referrals/capture
```

```json
{
  "tenant_key": "kotoa",
  "project_key": "sengoku",
  "source_system": "ai_art_school",
  "external_user_id": "12345",
  "referral_token": "signed-expiring-token",
  "captured_at": "2026-07-20T10:00:00+09:00"
}
```

### 6.3 紹介確定

```http
POST /api/referrals/confirm
```

```json
{
  "tenant_key": "kotoa",
  "project_key": "sengoku",
  "common_user_id": "CU-000001",
  "event_type": "ai_art.registration_completed",
  "referral_token": "signed-expiring-token",
  "occurred_at": "2026-07-20T10:05:00+09:00"
}
```

AIアート教室から `purchase_completed` や `subscription_started` を決済確定イベントとして送信しません。決済確定はショッピングシステムが通知します。

## 7. 共通イベント形式

Webhookは次の共通エンベロープを使用します。

```json
{
  "event_id": "evt_01J...",
  "event_type": "entitlement.granted",
  "event_version": 1,
  "occurred_at": "2026-07-20T10:10:00+09:00",
  "source_system": "shopping",
  "tenant_key": "kotoa",
  "project_key": "sengoku",
  "trace_id": "trc_01J...",
  "subject": {
    "common_user_id": "CU-000001"
  },
  "data": {
    "source_order_id": "ORD-0001",
    "entitlement_id": "ENT-0001",
    "entitlement_type": "ticket",
    "quantity": 6,
    "valid_until": "2027-01-20T23:59:59+09:00"
  }
}
```

### 7.1 署名

- 署名方式: HMAC-SHA256
- 署名対象: `{timestamp}.{raw_request_body}`
- ヘッダー: `X-Sengoku-Timestamp`、`X-Sengoku-Signature`
- 許容時刻差: 原則5分以内
- テナント単位でキーを分離し、`X-Sengoku-Key-Id` で識別する
- キー更新時は旧キーと新キーを一定期間併用できるようにする

### 7.2 冪等性

- 受信側は `event_id` を一意保存する
- 同じ `event_id` は再実行せず、成功済みレスポンスを返す
- API操作は `Idempotency-Key` を受け付ける

### 7.3 再送

- 2xx: 成功
- 4xx: リクエスト修正が必要。原則自動再送しない
- 429/5xx/通信失敗: 指数バックオフで再送
- 最大試行回数超過後はデッドレターへ保存し、管理画面に表示する

## 8. 決済と受講権

### 8.1 標準フロー

1. AIアート教室がショッピングシステムの購入画面へ遷移させる
2. ショッピングシステムが注文・決済を確定する
3. ショッピングシステムが `order.paid` を発行する
4. 受講権基盤が `entitlement.granted` を発行する
5. AIアート教室がローカル利用権へ反映する
6. 利用時、AIアート教室が消費要求を送る
7. 返金・取消時は `order.refunded` と `entitlement.revoked` を受け取る

### 8.2 AIアート教室が受信する主なイベント

- `order.paid`
- `order.refunded`
- `entitlement.granted`
- `entitlement.updated`
- `entitlement.revoked`
- `user.merged`
- `referral.confirmed`
- `agent.assignment_changed`

### 8.3 AIアート教室が送信する主なイベント

- `ai_art.registration_completed`
- `ai_art.reservation_created`
- `ai_art.reservation_cancelled`
- `ai_art.attendance_checked_in`
- `ai_art.generation_requested`
- `ai_art.generation_completed`
- `ai_art.entitlement_consumption_requested`

ポイント、クーポン、ガチャ残高は千ノ国ウォレットAPIを通じて操作し、AIアート教室DBへ正本として保持しません。

## 9. テナント分離

- すべての連携リクエスト、イベント、監査ログに `tenant_key` を含める
- 共通案件は `project_key` も含める
- DB検索は必ず現在の `tenant_id` または `tenant_key` で絞り込む
- テナント間でLINE User ID、Stripeキー、LIFF ID、APIキー、Webhook署名キーを共有しない
- 共通IDが同じでも、テナントごとの会員レコードと権限は分離する
- Webhook URLにテナント識別子を含める場合も、本文と署名から一致を検証する

## 10. SSO

AIアート教室から代理店システムへ遷移する開始URLは次を標準とします。

```text
/agent/sso_launch.php?client={client_key}
```

検証には `/api/sso/jwks.php` の公開鍵を使用します。JWTでは少なくとも `iss`、`aud`、`sub`、`exp`、`iat`、`jti`、`common_user_id`、`tenant_key` を検証します。

`aud` はURLのクエリ名として流用せず、JWTの受信者識別に使用します。

## 11. LINEアカウント連携

- `line_user_id` はLINE公式アカウントまたはMessaging APIチャネルごとに扱う
- テナントを跨いで同じ人物と推測して自動統合しない
- 共通IDとの紐付けは共通ID解決APIの結果を保存する
- ブロック、友だち解除、再追加時も過去の共通IDとの整合を確認する
- LINEプロフィール名は本人確認済み氏名として扱わない

## 12. 既存データ移行

### 12.1 移行対象

- AIアート教室会員
- LINE User ID
- 予約・出席
- 画像生成依頼・生成物
- ローカル決済履歴
- 回数券・サブスク・初回無料等のローカル利用権
- 紹介コード・担当者情報

### 12.2 移行手順

1. 全テーブルのテナント未割当件数を監査する
2. `tenant_key` と `ai_art_member_id` を確定する
3. メール、電話、LINE、既存外部IDを候補に共通ID照合を行う
4. 一意一致のみ自動確定する
5. 複数候補と不一致を `review_required` キューへ送る
6. 確定後に `/api/v2/user-mappings` へ外部ID対応を登録する
7. 決済履歴を `source_order_id` と照合する
8. 受講権を `entitlement_id` 単位に変換し、件数・有効期限を照合する
9. 紹介者、販売担当、現在担当を別項目へ分離する
10. 移行前後の件数・金額・残数を照合して監査記録を残す

既存データを削除・上書きせず、対応表と移行ログを残します。

## 13. セキュリティと個人情報

- APIキー、署名キー、Stripeキー、LINEトークンをログへ出力しない
- 個人情報をWebhookの不要な項目へ含めない
- ログにはIDを優先し、氏名、住所、電話、メールの平文出力を抑制する
- 管理画面ではオーナー、管理者、スタッフの権限を分離する
- 他テナントの設定・顧客・予約・生成物へアクセスできないことを自動テストする
- 退会・削除要求時は、法令上保持が必要な決済記録と削除可能なプロフィールを分ける

## 14. エラー契約

```json
{
  "ok": false,
  "error": {
    "code": "COMMON_USER_REVIEW_REQUIRED",
    "message": "共通ユーザーの確認が必要です",
    "retryable": false,
    "trace_id": "trc_01J..."
  }
}
```

各APIはHTTPステータスだけでなく、機械判定できる `error.code`、再送可否 `retryable`、追跡用 `trace_id` を返します。

## 15. レート制限

- APIはテナント・キー単位でレート制限する
- 429応答では `Retry-After` を返す
- 一括移行は通常APIと別の制限枠を使用する
- Webhook再送が通常処理を圧迫しないようキューを分離する

## 16. テスト項目

### 16.1 共通ID

- 新規作成、一意照合、複数候補、照合不能
- 同一リクエストの冪等再実行
- テナントを跨いだ誤紐付けが発生しない

### 16.2 紹介

- 確定済み紹介関係が最優先される
- 有効な紹介トークンが反映される
- 期限切れ・改ざんトークンが拒否される
- 紹介者、販売担当、現在担当が混同されない

### 16.3 決済・受講権

- 支払完了、返金、取消、回数券消費、サブスク更新・解約
- 同一Webhookの重複受信で二重付与されない
- AIアート教室のローカルStripe結果だけで全体確定しない

### 16.4 テナント

- 一覧、詳細、検索、CRON、Webhook、画像、予約がテナント分離される
- 別テナントのIDをURLへ指定しても404または403になる
- APIキー、LINE、LIFF、Stripe、画像生成設定が選択テナントへ切り替わる

### 16.5 障害

- 署名不正、期限切れ、429、5xx、タイムアウト
- 再送、デッドレター、管理画面からの再処理
- 部分成功時のロールバックまたは再開

## 17. 推奨実装順序

1. 本書のAPI契約とイベント名を5システム担当者で承認する
2. `tenant_key`、`project_key`、`ai_art_member_id` を全対象データへ付与する
3. 共通ID解決APIと外部IDマッピングAPIの役割を固定する
4. 署名、冪等性、エラー形式、監査ログを共通化する
5. 既存会員の共通ID移行を実施する
6. 紹介候補の捕捉と確定フローを移行する
7. ショッピング正本の決済・受講権Webhookを接続する
8. ポイント・クーポン・ガチャをウォレットAPIへ接続する
9. SSOと管理者連携を接続する
10. テナント横断の結合試験と段階リリースを行う

## 18. 現時点で改修が必要な項目

- 共通イベントエンベロープと署名検証
- `tenant_key` を含む全API・Webhook契約
- `common_user_id` の未照合・要確認状態管理
- `referral_token` の署名・期限検証
- 紹介者、販売担当、現在担当の分離
- ショッピング正本の決済・返金・受講権連携
- ウォレット正本のポイント・クーポン・ガチャ連携
- 既存データ移行ツールと照合レポート
- デッドレター、再送、監査ログ
- テナント分離の自動テスト

これらが完了するまで、既存APIは互換運用とし、既存利用元を破壊する変更は行いません。
