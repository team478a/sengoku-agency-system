# 戦国パスポート連携 確認事項への回答案

戦国パスポート開発チーム 御中

お世話になっております。  
「千ノ国 代理店システム 外部開発者向け連携ガイド v3.6.78-draft」に関するご確認事項について、現時点の回答を以下にまとめます。

---

## 1. イベント配信の失敗有無について

ご指摘の通り、戦国パスポート側の受信APIが `external_id` 必須の代理店作成・更新イベントのみを想定している場合、以下のイベントは 422 エラーになる可能性があります。

- `common_user.merged`
- `common_user.assigned_agent.updated`
- `lead_created`
- `role_updated`
- `approved`
- `promoted`
- `deactivated`
- `deleted`

代理店システム側では、外部連携ログとして `integration_event_logs` に送信結果を記録する設計です。  
本番環境の戦国パスポート宛ログについては、管理画面の「操作ログ / 外部連携ログ」またはDB上の `integration_event_logs` を確認し、失敗がある場合は以下を共有します。

- 送信先サイトキー
- event
- HTTPステータス
- レスポンス本文
- エラーメッセージ
- 送信日時

運用上は、戦国パスポート側の受信処理が対応するまで、以下のどちらかで進めるのが安全です。

1. 戦国パスポート側で未対応 `event` は 200 または 202 で受け取り、処理対象外として無視する
2. 代理店システム側で、戦国パスポート宛の送信イベントを一時的に代理店基本イベントのみに制限する

推奨は 1 です。  
理由は、今後イベント種別が増えても受信側が停止しにくくなるためです。

---

## 2. `system_key` の確定

戦国パスポートの `system_key` は、現時点では以下で確定する想定です。

```text
sengoku-passport
```

サービス名称が「千ノ国パスポート」へ変更される場合でも、システム連携キーは変更しない運用を推奨します。  
表示名は変更可能ですが、`system_key` はDB連携・共通顧客ID・外部ユーザー紐づけのキーになるため、将来にわたって固定する前提です。

---

## 3. 各APIの提供状況

以下のAPIは、代理店システム側の実装としては存在しています。

- `POST /api/common-users/resolve`
- `POST /api/common-users/{common_user_id}/system-links`
- `POST /api/referrals/capture`
- `POST /api/referrals/confirm`

ただし、本番利用には以下の前提があります。

- DBマイグレーションが適用済みであること
- `common_hub_enabled` が有効であること
- 書き込み系APIでは `common_hub_write_enabled` が有効であること
- 紹介系APIでは `referral_v2_enabled` が有効であること
- APIキー認証が正しく設定されていること

ガイドの `draft` 表記は、APIの思想・設計が今後拡張される可能性があるための表記です。  
実装済みAPIではありますが、戦国パスポート側との接続テスト完了後に `draft` を外すのがよいと考えています。

---

## 4. `referrals/capture` の呼び出しタイミング

LIFF環境では、以下の流れを推奨します。

1. `referral_token` 付きURLでLIFFが起動
2. LIFF初期化完了
3. `referral_token` を取得できた時点で `POST /api/referrals/capture` を呼び出す
4. 戦国パスポート側で `referral_session_key` を保存
5. LINEログインまたは会員登録が確定
6. `common_user_id` を解決
7. 申込・登録・購入など成果確定時に `POST /api/referrals/confirm` を呼び出す

`session_key` / `referral_session_key` については、どちらの発行でも対応可能です。

- 戦国パスポート側で発行して送信した場合: 代理店システム側はその値を使います
- 未指定の場合: 代理店システム側が `referral_session_key` を生成してレスポンスで返します

LIFFでは画面遷移や再読み込みが通常ブラウザと異なるため、戦国パスポート側で `referral_session_key` を保存しておくことを推奨します。

---

## 5. 既存の紹介関係データについて

既存の `referring_agent_id` を約1年分保持している件については、いきなり全件を `referrals/confirm` で遡及送信するのではなく、まず移行方針を決めるのが安全です。

推奨方針:

1. 戦国パスポート側の既存 `referring_agent_id` と代理店システム側の `agent_code` の対応表を作る
2. 既存ユーザーに対して `POST /api/common-users/resolve` を実行し、`common_user_id` を付与する
3. 必要な範囲だけ、過去の紹介関係を移行イベントとして登録する
4. 遡及成果は通常成果と区別できるよう、`event_type` や `metadata` に `legacy_import` を付ける

二重登録を避けるため、遡及送信時は `Idempotency-Key` に戦国パスポート側の既存IDを含めてください。

例:

```text
passport-legacy-referral-{user_id}-{referring_agent_id}
```

代理店システム側で既に把握している紹介データがある場合は、以下で突合します。

- `system_key = sengoku-passport`
- `external_user_id`
- `common_user_id`
- `agent_code`
- `referral_token`
- `occurred_at`

---

## 6. `default_commission_rate` の要否

`default_commission_rate` は必須ではありません。  
戦国パスポート側で代理店報酬の計算・確定を行わない場合は、省略して問題ありません。

代理店システム側を報酬計算の正とする場合、戦国パスポート側からは送信不要です。

---

## 7. その他軽微な確認事項

### 7.1 エラーレスポンス形式

代理店システム側の標準エラー形式は以下です。

```json
{
  "ok": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "..."
  }
}
```

一方で、代理店システムから戦国パスポート側へ送信した際のレスポンスについては、現時点では主に以下を記録・判定します。

- HTTPステータス
- レスポンス本文
- 通信成功/失敗

そのため、戦国パスポート側が現在返している以下の形式でも、HTTPステータスが成功系であれば致命的ではありません。

```json
{
  "success": false,
  "message": "..."
}
```

ただし、今後の自動再送・エラー分類・管理画面表示を考えると、将来的には代理店システム側の標準形式に寄せていただくのが望ましいです。

### 7.2 `include_sso=1` とSSO起動URL

`include_sso=1` を指定した場合、階層取得APIのレスポンスにSSO起動URLを含める設計です。

ただし、戦国パスポート側で現在のように以下の固定パターンで組み立てる方式でも問題ありません。

```text
https://sengoku-ai.com/agent/sso_launch.php?client={client_key}
```

`client_key` はSSO連携設定で登録したサイトキーを使ってください。  
例:

```text
https://sengoku-ai.com/agent/sso_launch.php?client=sengoku-passport
```

---

## 追加でこちら側が確認すること

以下は代理店システム側で本番環境を確認します。

- 戦国パスポート宛の `integration_event_logs` に失敗があるか
- 失敗している場合のHTTPステータスとレスポンス本文
- 戦国パスポート宛に送信するイベントを一時制限する必要があるか
- 外部開発者向けガイドの `draft` 表記をいつ外すか

以上です。  
よろしくお願いいたします。
