# LP・問い合わせ紹介トークン連携 v3.6.62

## 目的

代理店LPから問い合わせが入った時に、どの代理店・どのプロジェクト・どの紹介トークン経由かを後から追跡できるようにします。

既存の `leads.agent_id` は維持し、追加情報として共通IDと紹介トークンを保存します。

## 追加されるDB項目

`leads`:

```text
common_user_id
referral_token_id
referral_session_key
referral_source
```

`access_logs`:

```text
referral_token_id
referral_session_key
```

## LP表示時の動作

LP表示時に、代理店・プロジェクト単位で紹介トークンを自動発行します。

URLに `rt` が指定されている場合は、そのトークンを検証して利用します。
指定がない場合は、現在表示中の代理店・プロジェクト用トークンを自動作成します。

LPアクセスは `referral_sessions` に `event_type=lp_view` として保存されます。

## 問い合わせ時の動作

問い合わせフォーム送信時に、以下を保存します。

- `leads` への問い合わせ情報
- `common_users` への共通ユーザー情報
- `agency_customer_relations` への代理店紹介関係
- `referral_sessions` への `event_type=lead` 記録

共通ID連携がOFF、またはマイグレーション未適用の場合は、従来の問い合わせ保存のみ実行されます。

## LPテンプレートで使えるタグ

```text
{{referral_token}}
{{referral_session_key}}
{{referral_query}}
{{referral_lp_url}}
{{referral_hidden_fields}}
```

既存テンプレートには `{{referral_hidden_fields}}` が自動挿入されます。
新規テンプレートでは、問い合わせフォーム内に明示的に配置できます。

```html
<form>
  {{referral_hidden_fields}}
</form>
```

## 必要な設定

管理画面の共通ID連携で以下をONにしてください。

```text
common_id_enabled
referral_v2_enabled
referral_token_api_enabled
```
