# 外部連携ドキュメント整理メモ

作成日: 2026-08-17

## 対応内容

外部連携まわりの説明に残っていた文字化けと、資料間の表記ゆれを整理しました。

## 更新ファイル

- `admin/integration_guide.php`
- `docs/integration/EXTERNAL_DEVELOPER_GUIDE.md`
- `docs/integration/EXTERNAL_INTEGRATION_SETUP_FLOW_20260803.md`
- `docs/integration/EXTERNAL_DEVELOPER_HANDOFF.md`

## 統一した仕様

- 共通顧客ID解決APIは `POST /api/common-users/resolve` を正式パスとする。
- `/api/v2/common-users/resolve` は外部公開用の正式パスとして案内しない。
- 案件、商品、LPの識別子は `project_key` を正式名とする。
- `project_key` の値はプロジェクト管理のスラッグと同じ。
- 既存互換として `project_slug` を受け取れる設計は許容する。
- 紹介流入API `POST /api/referrals/capture` は継続利用する。
- 同一外部サービスではAPIキーを1つにし、商品差分は `project_key` と `product_code` で区別する。
- 代理店の外部識別子は `agent_code` を優先する。
- SSO連携先の識別子は `site_key` に統一する。

## 検証

- `admin/integration_guide.php` のPHP構文チェックを実施。
- 更新対象4ファイルに、代表的な文字化け文字列が残っていないことを確認。

## DB変更

なし。

## API変更

なし。今回はドキュメントと管理画面ガイドの整理のみです。
