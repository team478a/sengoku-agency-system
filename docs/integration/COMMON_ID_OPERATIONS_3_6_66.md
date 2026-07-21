# 共通ID修正操作 v3.6.66

## 目的

外部サービスが増えた場合に、誤った共通ID紐づけや紹介関係を管理画面から修正できるようにします。

## 対象画面

- `/admin/common_id_mappings.php`

## 追加した操作

スーパー管理者のみ実行できます。

- サービス別ユーザー紐づけの代理店変更
- サービス別ユーザー紐づけの状態変更
- 紹介関係の代理店変更
- 紹介関係の状態変更
- 紹介関係の固定/解除
- 共通IDの統合

## 共通ID統合の仕様

統合元の共通IDを統合先の共通IDへ寄せます。

- `service_user_mappings.common_user_id` を統合先へ変更
- `agency_customer_relations` は、統合先に同じ `relation_type + project_id` がない場合だけ移動
- 競合する紹介関係は `inactive` に変更して履歴を残す
- `integration_event_logs.common_user_id` は統合先へ変更
- 統合元 `common_users.status` は `merged` に変更

## 監査ログ

以下の操作は `admin_action_logs` に記録されます。

- `common_id_mapping_update`
- `common_id_relation_update`
- `common_id_merge`

## 注意

この機能は削除ではなく、状態変更・移動・統合です。
外部システム連携に影響するため、操作前に対象の共通IDと外部サービス側IDを必ず確認してください。
