# External Integration Simplification Phase S04

Version: 3.6.133
Date: 2026-08-03

## Purpose

Make the external partner settings page easier to operate by separating the two outbound endpoint types:

- Agency sync endpoint: agency registration/update/stop/delete events.
- Common event endpoint: leads, common users, purchase/payment and other shared events.

## Changes

- `admin/external_partners.php`
  - Adds endpoint split awareness for `agency_sync_endpoint` and `common_event_endpoint`.
  - Saves both endpoint columns when the database migration from 3.6.132 has been applied.
  - Keeps the old single `base_url` behavior when the new columns are not available.
  - Shows both resolved endpoints in the partner list.

## Database

No new migration in this phase.

This phase depends on the 3.6.132 migration:

- `external_partner_sites.agency_sync_endpoint`
- `external_partner_sites.common_event_endpoint`

## Compatibility

Existing partner settings still work. If the two endpoint fields are empty, endpoints are automatically derived from the base URL.

