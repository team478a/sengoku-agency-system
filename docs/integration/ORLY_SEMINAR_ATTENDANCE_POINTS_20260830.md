# ORLY説明会参加ポイント

作成日: 2026-08-30

## カウント方法

説明会参加は、新規登録とは別のイベントとして扱います。

```text
seminar_attendance:{seminar_id}:{common_user_id}
```

同じ参加者が同じ説明会で複数回送信されても、同じポイント付与候補として扱います。  
同じキーで受取人やポイント数が変わる場合は競合として停止します。

## 付与ルール

| 対象 | ポイント | recipient_type |
| --- | ---: | --- |
| 参加本人 | 10,000 | `attendee` |
| 直接紹介者 | 11,000 | `direct_referrer` |
| 上位ディレクター | 11,000 | `upper_director` |

新規登録本人の3,000ポイントは、既存の `orly_referral_signup` キャンペーンで扱います。

## API

```text
POST /api/points/seminar-attendance
```

入力例:

```json
{
  "seminar_id": "seminar_20260830_tokyo_01",
  "common_user_id": "cu_xxx",
  "direct_referrer_agent_id": 123,
  "project_key": "orly",
  "source_system_key": "AGENCY_SYSTEM",
  "occurred_at": "2026-08-30T10:00:00+09:00"
}
```

`direct_referrer_agent_id` を省略した場合は、既存の代理店紐づきから紹介者を取得します。

## 有効化条件

次の3つがすべてONの場合だけ動作します。

- `orly_point_campaign_enabled`
- `orly_point_award_enabled`
- `orly_seminar_point_award_enabled`

初期値はすべてOFFです。さらにキャンペーン `orly_seminar_attendance` は初期状態 `draft` です。

## Wallet送信

この実装ではWalletへ即時送信せず、まず `point_award_events` に `pending` として保存します。  
Wallet送信は `orly_wallet_delivery_enabled` とWallet側接続設定の確認後に別工程で有効化します。
