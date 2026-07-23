# Rollback Guide

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
