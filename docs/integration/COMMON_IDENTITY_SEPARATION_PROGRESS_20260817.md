# 共通ID・代理店分離 改修進捗 2026-08-17

## 実施したこと

共通ID解決APIの中に直接書かれていた処理を、専用サービスへ分離しました。
外部公開URLとレスポンス形式は変更していません。

## 追加・更新ファイル

- `src/CommonIdentity/CommonUserResolveService.php`
- `includes/shared_bootstrap.php`
- `api/common-users/index.php`
- `tests/Characterization/CommonIdentityFoundationTest.php`

## 内容

- 共通ユーザー解決処理を `CommonUserResolveService` へ移動
- システムアカウント紐づけ保存処理を `CommonUserResolveService` へ移動
- `api/common-users/index.php` は既存関数名を残しつつ、新サービスへ処理を委譲
- 存在しない `api/v2/common-users/resolve/index.php` をテスト対象から除外
- `CommonUserResolveService` が共通読み込みファイルから読み込まれるように追加

## 確認結果

- `src/CommonIdentity/CommonUserResolveService.php`: 構文OK
- `api/common-users/index.php`: 構文OK
- `includes/shared_bootstrap.php`: 構文OK
- `tests/Characterization/CommonIdentityFoundationTest.php`: 構文OK
- `includes/shared_bootstrap.php` 経由で `CommonUserResolveService` が読み込まれることを確認

## 未実施

- PHPUnit実行。対象フォルダに `vendor/bin/phpunit` が存在しないため未実行。
- DB構造変更。
- API公開URLの変更。
- SSO仕様の変更。
- LP、紹介URL、既存代理店管理画面の仕様変更。

## 次に進めること

1. `agency_customer_relations` を主データとして扱う読み取りサービスを追加
2. `common_users` 上の代理店系フィールドを互換用に限定
3. 代理店が存在しない一般ユーザー登録のテストを拡充
4. 外部開発者向けドキュメントへ「正式APIパスは `/api/common-users/resolve`」を再明記
