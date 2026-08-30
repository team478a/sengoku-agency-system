-- 3.6.167 千ノ国インフルエンサー向け4種LPテンプレート追加

INSERT IGNORE INTO lp_templates (project_id, slug, name, description, html_file, sort_order, status) VALUES
((SELECT id FROM projects WHERE slug='sengoku-influencer' LIMIT 1), 'sen-no-kuni-influencer-women', '千ノ国インフルエンサー 女性向け', '女性向けの千ノ国インフルエンサーLP', 'sen-no-kuni-influencer-women.php', 31, 'active'),
((SELECT id FROM projects WHERE slug='sengoku-influencer' LIMIT 1), 'sen-no-kuni-influencer-men', '千ノ国インフルエンサー 男性向け', '男性向けの千ノ国インフルエンサーLP', 'sen-no-kuni-influencer-men.php', 32, 'active'),
((SELECT id FROM projects WHERE slug='sengoku-influencer' LIMIT 1), 'sen-no-kuni-influencer-women-30-40', '千ノ国インフルエンサー 30〜40代女性向け', '30〜40代女性向けの千ノ国インフルエンサーLP', 'sen-no-kuni-influencer-women-30-40.php', 33, 'active'),
((SELECT id FROM projects WHERE slug='sengoku-influencer' LIMIT 1), 'sen-no-kuni-influencer-activity', '千ノ国インフルエンサー 活動紹介', '活動内容を伝える千ノ国インフルエンサーLP', 'sen-no-kuni-influencer-activity.php', 34, 'active');

INSERT IGNORE INTO schema_migrations (version, description)
VALUES ('3.6.167', '千ノ国インフルエンサー向け4種LPテンプレート追加');
