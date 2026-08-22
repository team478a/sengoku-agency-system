# External Integration Simplification Phase S05

Version: 3.6.134
Date: 2026-08-03

## Purpose

Add a beginner-friendly setup wizard for external partner integrations.

## Added

- `admin/external_partner_wizard.php`
  - Step-by-step explanation for external service integration.
  - Simple partner registration form.
  - Automatic API key generation.
  - Automatic agency sync endpoint and common event endpoint derivation.
  - Copyable values for external developers.
  - Existing partner summary with next-action links.

## Updated

- `admin/header.php`
  - Adds `連携ウィザード` to the admin side menu.

## Database

No new migration.

The wizard uses the existing `external_partner_sites` table.
When 3.6.132 endpoint split columns are available, it stores:

- `agency_sync_endpoint`
- `common_event_endpoint`

If those columns are not available, the wizard keeps compatibility with the older single `base_url` behavior.

