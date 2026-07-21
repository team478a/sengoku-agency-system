# 外部連携ドキュメント索引

外部開発会社へ最初に共有する資料は、以下のガイドです。

- [千ノ国 代理店システム 外部開発者向け連携ガイド](EXTERNAL_DEVELOPER_GUIDE.md)
- [千ノ国 代理店システム 共通顧客HUB 外部連携契約](COMMON_HUB_EXTERNAL_CONTRACT_2026-07-21.md)
- [千ノ国 代理店システム 共通顧客HUB 受入テスト表](COMMON_HUB_ACCEPTANCE_TESTS_2026-07-21.md)
- [AIアート教室 連携フロー・実装ガイド](AI_ART_SCHOOL_INTEGRATION_GUIDE.md)

このガイドでは、APIキーの考え方、通信方向、代理店階層API、共通顧客ID、紹介URL、外部送信イベント、SSO/JWT検証方法をまとめています。

## 補足資料

- [現在のAPI一覧](CURRENT_API_INVENTORY.md)
- [現在のDB構成](CURRENT_DB_SCHEMA.md)
- [現在のアーキテクチャ](CURRENT_ARCHITECTURE.md)
- [共通顧客HUB API Stage2](COMMON_CUSTOMER_HUB_API_STAGE2.md)
- [API v2 共通ID](API_V2_COMMON_ID.md)
- [外部送信イベント](OUTBOUND_EVENTS_3_6_63.md)
- [紹介URLとLP連携](LP_REFERRAL_CONNECTION_3_6_62.md)
- [PHPからReact/API化への移行計画](PHP_API_REACT_TRANSITION_PLAN.md)

## 共有時の注意

APIキー、JWT署名鍵、DB接続情報などの秘密情報は、このリポジトリやドキュメントに直接記載しないでください。
外部会社へ渡す値は、管理画面で発行したキーを安全な方法で個別共有してください。
