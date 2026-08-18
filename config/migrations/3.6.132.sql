-- v3.6.132: Split external partner outbound endpoints by purpose.

ALTER TABLE external_partner_sites
    ADD COLUMN IF NOT EXISTS agency_sync_endpoint VARCHAR(500) DEFAULT NULL AFTER base_url,
    ADD COLUMN IF NOT EXISTS common_event_endpoint VARCHAR(500) DEFAULT NULL AFTER agency_sync_endpoint;

UPDATE external_partner_sites
SET
    agency_sync_endpoint = CASE
        WHEN COALESCE(agency_sync_endpoint, '') <> '' THEN agency_sync_endpoint
        WHEN base_url LIKE '%/api/integrations/events' THEN REPLACE(base_url, '/api/integrations/events', '/api/integrations/agencies')
        WHEN base_url LIKE '%/api/integrations/agencies' THEN base_url
        ELSE CONCAT(TRIM(TRAILING '/' FROM base_url), '/api/integrations/agencies')
    END,
    common_event_endpoint = CASE
        WHEN COALESCE(common_event_endpoint, '') <> '' THEN common_event_endpoint
        WHEN base_url LIKE '%/api/integrations/events' THEN base_url
        WHEN base_url LIKE '%/api/integrations/agencies' THEN REPLACE(base_url, '/api/integrations/agencies', '/api/integrations/events')
        ELSE CONCAT(TRIM(TRAILING '/' FROM base_url), '/api/integrations/events')
    END
WHERE COALESCE(base_url, '') <> '';

INSERT IGNORE INTO schema_migrations (version, description)
VALUES ('3.6.132', 'Split external partner agency sync and common event endpoints');
