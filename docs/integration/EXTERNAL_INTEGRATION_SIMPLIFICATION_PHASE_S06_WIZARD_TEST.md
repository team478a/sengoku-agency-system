# External Integration Simplification - Phase S06

Version: 3.6.135

## Purpose

Make the external integration wizard usable as the first setup screen by allowing connection tests directly from the wizard list.

## Changes

- Added `test_partner_site` POST handling to `admin/external_partner_wizard.php`.
- The wizard now calls `testExternalPartnerSiteConnection()` directly.
- Test results are shown on the wizard page.
- When the `external_partner_sites.last_test_*` columns exist, the wizard records the latest test status, time, and message.
- Added an explicit `action=create_partner_site` field to the create form to avoid mixing create and test requests.

## Database

No new migration in this phase.

This feature uses existing columns when available:

- `external_partner_sites.last_test_status`
- `external_partner_sites.last_test_at`
- `external_partner_sites.last_test_message`

## Verification

- `php -l admin/external_partner_wizard.php`

