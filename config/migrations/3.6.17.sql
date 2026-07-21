INSERT IGNORE INTO projects (slug, name, description, status, sort_order)
VALUES ('ai-art-school', 'AIアート教室', 'AIアート教室・無料体験向けLPプロジェクト', 'active', 20);

INSERT IGNORE INTO lp_templates (project_id, slug, name, description, html_file, sort_order, status) VALUES
((SELECT id FROM projects WHERE slug='ai-art-school' LIMIT 1), 'ai-art-illustration-pop', 'AIアート教室 イラストPOP', 'イラスト・ポップ訴求のAIアートLP', 'ai-art-illustration-pop.php', 20, 'active'),
((SELECT id FROM projects WHERE slug='ai-art-school' LIMIT 1), 'ai-art-young-sns', 'AIアート教室 若年層SNS', '若年層・SNS訴求のAIアートLP', 'ai-art-young-sns.php', 21, 'active'),
((SELECT id FROM projects WHERE slug='ai-art-school' LIMIT 1), 'ai-art-senior-salon', 'AIアート教室 シニアサロン', 'シニア・サロン訴求のAIアートLP', 'ai-art-senior-salon.php', 22, 'active'),
((SELECT id FROM projects WHERE slug='ai-art-school' LIMIT 1), 'ai-art-lp-illustration', 'AIアート教室 イラスト', 'イラスト訴求のAIアートLP', 'ai-art-lp-illustration.php', 23, 'active'),
((SELECT id FROM projects WHERE slug='ai-art-school' LIMIT 1), 'ai-art-lp-older', 'AIアート教室 大人向け', '大人・シニア向けAIアートLP', 'ai-art-lp-older.php', 24, 'active'),
((SELECT id FROM projects WHERE slug='ai-art-school' LIMIT 1), 'ai-art-lp-young', 'AIアート教室 若年層向け', '若年層向けAIアートLP', 'ai-art-lp-young.php', 25, 'active');

INSERT IGNORE INTO schema_migrations (version, description)
VALUES ('3.6.17', 'AIアートLPテンプレート追加');
