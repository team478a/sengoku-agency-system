# 代理店システム 共通連携改修 v3.6.78

## 目的

千ノ国プロジェクトの5システム連携に向けて、代理店システムを共通顧客HUBとして扱うための互換改修です。既存API・既存代理店管理・既存LP/SSO連携は維持し、外部連携で必要になる識別子、紹介トークン、APIキー、イベント追跡を追加しています。

## 共通識別子

- `common_user_id` は代理店システムが発行します。形式は既存通り `cu_` + 32桁hexです。
- 外部向けの代理店IDは `agency_id` を使用します。値は `agents.agent_code` です。
- 既存互換のため `external_id` と `agent_code` / `code` は残しています。
- 内部DB用の `agents.id` は `internal_agent_id` として参考返却しますが、外部システムの永続キーには使わないでください。

## 自動名寄せルール

- メール、電話、ウォレットは `verified=1` の本人確認済みIDだけ自動名寄せします。
- 未検証IDが一致しても自動マージせず、`identity_match_status=unverified_candidate_not_auto_merged` と `unverified_identity_candidates_count` で通知します。
- LINEユーザーIDは既存運用通り、連携元が本人性を担保する前提で保存時に verified として扱います。

## 紹介トークン

`POST /api/referrals/capture` と `POST /api/referrals/confirm` は、以下を受け付けます。

- 正規トークン: `referral_token` / `token` (`rt_...`)
- 旧紹介コード: `ref`
- カート側紹介コード: `referral_code`
- ウォレット招待: `invite_token`
- 任意指定: `alias_type`

旧コードは `referral_aliases` で正規トークンに解決します。レスポンスでは `canonical_referral_token` と `agency_id` を返します。

## 売上・権限ロール

`POST /api/referrals/confirm` で購入・登録確定情報がある場合、`customer_transactions` に以下を保存できます。

- `registration_referrer_agency_id`
- `assigned_agency_id`
- `sales_agent_id`
- `closing_agent_id`

`order_id` がない場合は、既存の紹介確定処理のみ実行し、取引行は作成しません。

## APIキーとセキュリティ

`external_partner_sites` に連携先ごとの設定を追加しました。

- `inbound_api_key`: 外部システムから代理店システムへ送信する時のキー
- `api_key`: 代理店システムから外部システムへ送信する時の連携先発行キー
- `inbound_scopes`: 空欄なら従来互換。設定時は `common_users:read`, `common_users:write`, `referrals:write`, `agencies:read`, `agencies:write` などを検査
- `api_key_expires_at`: 期限
- `inbound_ip_allowlist`: IP制限
- `hmac_secret`, `hmac_key_id`: 代理店システムから外部へ送るWebhook署名用

Idempotency-Key は同じキーで異なる本文が再送された場合、409 `IDEMPOTENCY_CONFLICT` を返します。

## Webhook送信

代理店システムから外部サイトへ送るJSONには以下を付与します。

- `event_id`
- `event_type`
- `event_version`
- `source_system_key=agency-system`
- `correlation_id`
- `occurred_at`

HTTPヘッダーには以下を付与します。

- `x-api-key`
- `Authorization: Bearer`
- `Idempotency-Key`
- `X-Event-Version`
- `X-Correlation-Id`
- `X-SenNoKuni-Key-Id`
- `X-SenNoKuni-Timestamp`
- `X-SenNoKuni-Nonce`
- `X-SenNoKuni-Signature`

署名文字列は `timestamp + "\n" + nonce + "\n" + raw_json_body` を `HMAC-SHA256` で署名し、`sha256=<hex>` 形式で送ります。

## Outbox

既存の即時送信は残したまま、送信ごとに以下へ記録します。

- `integration_outbox_events`
- `integration_event_attempts`
- 既存の `integration_event_logs`

これにより、今後の自動再送、DLQ、管理画面からの再送に拡張できます。

