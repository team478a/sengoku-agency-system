INSERT INTO point_campaigns (campaign_key, point_currency_code, name, status)
VALUES ('orly_seminar_attendance', 'orly', 'ORLY説明会参加ポイント', 'draft')
ON DUPLICATE KEY UPDATE
    point_currency_code=VALUES(point_currency_code),
    name=VALUES(name);

INSERT INTO point_campaign_versions
    (campaign_id, version_no, registrant_points, direct_referrer_points, upper_director_points, status, rule_json)
SELECT id, 1, 10000, 11000, 11000, 'draft',
       '{"trigger":"seminar.attended","target_recipient_type":"attendee","idempotency":"seminar_id+common_user_id","wallet_delivery":"disabled_until_feature_flag_enabled"}'
FROM point_campaigns
WHERE campaign_key = 'orly_seminar_attendance'
ON DUPLICATE KEY UPDATE
    registrant_points=VALUES(registrant_points),
    direct_referrer_points=VALUES(direct_referrer_points),
    upper_director_points=VALUES(upper_director_points),
    rule_json=VALUES(rule_json);

INSERT IGNORE INTO system_settings (key_name, value) VALUES
('orly_seminar_point_award_enabled', '0');

INSERT IGNORE INTO schema_migrations (version, description)
VALUES ('3.6.170', 'ORLY説明会参加ポイント付与候補');
