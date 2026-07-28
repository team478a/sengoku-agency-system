# Rollback Guide

Updated: 2026-07-29

This file is the Phase 0-2 rollback guide requested by the modular-monolith foundation instructions.

## Phase 0-2 Documentation Update Rollback

The Phase 0-2 handoff documentation update only adds or updates documentation plus version metadata.

Rollback options:

- Use `git revert` for the documentation commit.
- Or upload the previous ZIP through `admin/update.php` if the server must return to the prior package state.

No database rollback is required for this documentation-only update.

## v3.6.99

Rollback is file-only:

1. Revert the v3.6.99 commit.
2. Confirm `buildLpSeoMeta()` and `injectLpSeoHead()` contain their previous inline SEO generation logic.
3. Confirm LP pages still render `<title>`, description, canonical, OG, Twitter, and JSON-LD tags.
4. No database rollback is required.

## v3.6.98

Rollback is file-only:

1. Revert the v3.6.98 commit.
2. Confirm `agent/dashboard.php` builds the trend arrays inline again.
3. Confirm `agent/reports.php` builds downline ranking rows inline again.
4. No database rollback is required.

## v3.6.97

Rollback is file-only:

1. Revert the v3.6.97 commit.
2. Confirm `admin/agent_activity.php` and `agent/downline_activity.php` render summary cards inline again.
3. No database rollback is required.

## v3.6.96

Rollback is file-only:

1. Revert the v3.6.96 commit.
2. Confirm `.github/workflows/ci.yml` no longer starts the MariaDB service for CSV contract tests.
3. No database rollback is required.

## v3.6.95

Rollback is file-only:

1. Revert the v3.6.95 commit.
2. Remove the `composer test:csv-contract` script entry if reverting manually.
3. No database rollback is required.

## v3.6.94

Rollback is file-only:

1. Revert the v3.6.94 commit.
2. Confirm login-log CSV exports use their previous inline SQL again.
3. No database rollback is required.

## v3.6.93

Rollback is file-only:

1. Revert the v3.6.93 commit.
2. Confirm template report CSV exports use their previous inline SQL again.
3. No database rollback is required.

## v3.6.92

Rollback is file-only:

1. Revert the v3.6.92 commit.
2. Confirm recruitment-link CSV exports use their previous inline SQL again.
3. No database rollback is required.

## v3.6.91

Rollback is file-only:

1. Revert the v3.6.91 commit.
2. Confirm sub-agent CSV exports use their previous inline SQL again.
3. No database rollback is required.

## v3.6.90

Rollback is file-only:

1. Revert the v3.6.90 commit.
2. Confirm lead CSV exports use their previous inline SQL again.
3. No database rollback is required.

## v3.6.89

Rollback is file-only:

1. Revert the v3.6.89 commit.
2. Confirm activity CSV exports use their previous inline SQL again.
3. No database rollback is required.

## v3.6.88

Rollback is file-only:

1. Revert the v3.6.88 commit.
2. Confirm `admin/agent_activity.php` and `agent/downline_activity.php` use their previous inline activity SQL again.
3. No database rollback is required.

## v3.6.87

Rollback is file-only:

1. Revert the v3.6.87 commit.
2. Confirm LP helper functions and mail template replacement use their previous inline logic again.
3. No database rollback is required.

## v3.6.86

Rollback is file-only:

1. Revert the v3.6.86 commit.
2. Confirm `admin/integration_outbox.php` and `admin/operations.php` use their previous inline helper logic again.
3. No database rollback is required.

## v3.6.85

Rollback is file-only:

1. Revert the v3.6.85 commit.
2. Confirm common user and referral APIs use their previous inline normalization and hash logic again.
3. No database rollback is required.

## v3.6.84

Rollback is file-only:

1. Revert the v3.6.84 commit.
2. Confirm `admin/integration_outbox.php` and `cron/external_integration_retry.php` call the previous inline Outbox functions.
3. No database rollback is required.

## v3.6.83

Rollback is file-only:

1. Revert the v3.6.83 commit.
2. Confirm hierarchy API, agency sync API, and API v2 endpoints authenticate using their legacy inline functions again.
3. No database rollback is required.

## v3.6.82

Rollback is file-only:

1. Revert the v3.6.82 commit.
2. Remove generated `vendor/`, `.phpunit.cache/`, and `.phpstan-cache/` if they exist.
3. No database rollback is required.
